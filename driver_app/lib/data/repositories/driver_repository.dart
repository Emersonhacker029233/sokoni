import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/dio_client.dart';
import '../../core/storage/app_database.dart';
import '../api/driver_api.dart';
import '../models/delivery_offer.dart';
import '../models/driver_job.dart';
import '../models/driver_profile.dart';
import '../models/earnings.dart';

/// A value plus whether it came from the on-device cache because the
/// network was unreachable.
class Cached<T> {
  const Cached(this.value, {this.fromCache = false});

  final T value;
  final bool fromCache;
}

/// Thrown when the driver routes answer 404: the server has delivery
/// switched off (`DELIVERY_ENABLED=false`).
class DeliveryDisabledException implements Exception {
  const DeliveryDisabledException();
}

/// Everything under `/api/driver/*`. Reads that matter on a dead
/// connection (profile, active job, earnings) fall back to the last good
/// copy in drift.
class DriverRepository {
  DriverRepository({required DriverApi api, required Dio dio, required AppDatabase db})
      : _api = api,
        _dio = dio,
        _db = db;

  final DriverApi _api;
  final Dio _dio;
  final AppDatabase _db;

  static const _meKey = 'cache.driver_me';
  static const _activeJobKey = 'cache.active_job';
  static String _earningsKey(String period) => 'cache.earnings.$period';

  // ---- Profile and onboarding ------------------------------------------

  /// Null until the account has a driver profile.
  Future<Cached<DriverProfile?>> me() async {
    try {
      final json = await _api.me() as Map<String, dynamic>;
      final data = json['data'];
      await _db.setValue(_meKey, jsonEncode(data));
      return Cached(data == null ? null : DriverProfile.fromJson(data as Map<String, dynamic>));
    } catch (e) {
      final error = _map(e);
      if (isConnectivityError(error)) {
        final cached = await _db.getValue(_meKey);
        if (cached != null) {
          final data = jsonDecode(cached);
          return Cached(data == null ? null : DriverProfile.fromJson(data as Map<String, dynamic>), fromCache: true);
        }
      }
      throw error;
    }
  }

  Future<DriverProfile> saveProfile({
    required String nationalIdNumber,
    String? licenceNumber,
    String? licenceExpiresOn,
    required String termsVersion,
  }) {
    return _profileCall(() => _api.saveProfile({
          'national_id_number': nationalIdNumber,
          'licence_number': licenceNumber,
          'licence_expires_on': licenceExpiresOn,
          'accept_terms': true,
          'terms_version': termsVersion,
        }));
  }

  Future<DriverProfile> saveVehicle({required String type, String? plateNumber, String? make, String? colour}) {
    return _profileCall(() => _api.saveVehicle({
          'type': type,
          'plate_number': type == 'bicycle' ? null : plateNumber,
          'make': make,
          'colour': colour,
        }));
  }

  /// [files] maps `photo` / `national_id_image` / `licence_image` to a local path.
  Future<DriverProfile> uploadDocuments(Map<String, String> files, {void Function(double progress)? onProgress}) async {
    try {
      final form = FormData.fromMap({
        for (final entry in files.entries) entry.key: await MultipartFile.fromFile(entry.value, filename: entry.value.split(Platform.pathSeparator).last),
      });
      final response = await _dio.post<dynamic>(
        '/driver/profile/documents',
        data: form,
        onSendProgress: (sent, total) {
          if (total > 0) onProgress?.call(sent / total);
        },
      );
      return await _storeProfile(response.data);
    } catch (e) {
      throw _map(e);
    }
  }

  Future<DriverProfile> submit() => _profileCall(_api.submit);

  Future<void> registerDevice({required String fcmToken, required String platform}) async {
    try {
      await _api.registerDevice({'fcm_token': fcmToken, 'platform': platform});
    } catch (e) {
      throw _map(e);
    }
  }

  // ---- Availability and location ---------------------------------------

  Future<bool> setAvailability({required bool online, double? lat, double? lng}) async {
    try {
      final json = await _api.setAvailability({'online': online, 'lat': lat, 'lng': lng}) as Map<String, dynamic>;
      return (json['data'] as Map<String, dynamic>)['is_online'] == true;
    } catch (e) {
      throw _map(e);
    }
  }

  Future<void> sendLocation({required double lat, required double lng, double? heading, double? speed, double? accuracy}) async {
    try {
      await _api.sendLocation({
        'lat': lat,
        'lng': lng,
        if (heading != null && heading >= 0) 'heading': heading.round() % 360,
        if (speed != null && speed >= 0) 'speed': speed,
        if (accuracy != null && accuracy >= 0) 'accuracy': accuracy,
      });
    } catch (e) {
      throw _map(e);
    }
  }

  // ---- Offers -----------------------------------------------------------

  Future<List<DeliveryOffer>> offers() async {
    try {
      final json = await _api.offers() as Map<String, dynamic>;
      return (json['data'] as List).map((o) => DeliveryOffer.fromJson(o as Map<String, dynamic>)).toList();
    } catch (e) {
      throw _map(e);
    }
  }

  Future<DriverJob> accept(int jobId) => _jobCall(() => _api.accept(jobId));

  Future<void> decline(int jobId) async {
    try {
      await _api.decline(jobId);
    } catch (e) {
      throw _map(e);
    }
  }

  // ---- Jobs -------------------------------------------------------------

  Future<Cached<DriverJob?>> activeJob() async {
    try {
      final json = await _api.activeJob() as Map<String, dynamic>;
      final data = json['data'];
      await _db.setValue(_activeJobKey, jsonEncode(data));
      return Cached(data == null ? null : DriverJob.fromJson(data as Map<String, dynamic>));
    } catch (e) {
      final error = _map(e);
      if (isConnectivityError(error)) {
        final cached = await cachedActiveJob();
        return Cached(cached, fromCache: true);
      }
      throw error;
    }
  }

  Future<DriverJob?> cachedActiveJob() async {
    final cached = await _db.getValue(_activeJobKey);
    if (cached == null) return null;
    final data = jsonDecode(cached);
    return data == null ? null : DriverJob.fromJson(data as Map<String, dynamic>);
  }

  /// Keeps the offline copy in step with a status the outbox will send later.
  Future<void> cacheActiveJob(DriverJob? job) => _db.setValue(_activeJobKey, jsonEncode(job?.toJson()));

  Future<({List<DriverJob> jobs, bool hasMore})> pastJobs(int page) async {
    try {
      final json = await _api.jobs(page) as Map<String, dynamic>;
      final jobs = (json['data'] as List).map((j) => DriverJob.fromJson(j as Map<String, dynamic>)).toList();
      final meta = json['meta'] as Map<String, dynamic>?;
      final hasMore = meta != null && (meta['current_page'] as int? ?? page) < (meta['last_page'] as int? ?? page);
      return (jobs: jobs, hasMore: hasMore);
    } catch (e) {
      throw _map(e);
    }
  }

  Future<DriverJob> job(int jobId) => _jobCall(() => _api.job(jobId));

  /// [proofPhotoPath] turns the request into multipart (delivered only).
  Future<DriverJob> updateStatus(int jobId, String status, {Map<String, dynamic> extra = const {}, String? proofPhotoPath}) async {
    try {
      final body = {'status': status, ...extra};
      final dynamic json;
      if (proofPhotoPath != null) {
        final response = await _dio.post<dynamic>(
          '/driver/jobs/$jobId/status',
          data: FormData.fromMap({...body, 'proof_photo': await MultipartFile.fromFile(proofPhotoPath)}),
        );
        json = response.data;
      } else {
        json = await _api.updateStatus(jobId, body);
      }
      return await _storeJob(json);
    } catch (e) {
      throw _map(e);
    }
  }

  /// Returns the job's new call count (the API answers with just that).
  Future<int> callAttempt(int jobId) async {
    try {
      final json = await _api.callAttempt(jobId) as Map<String, dynamic>;
      return ((json['data'] as Map<String, dynamic>)['call_attempts'] as num).toInt();
    } catch (e) {
      throw _map(e);
    }
  }

  Future<void> rateCustomer(int jobId, {required int rating, String? comment}) async {
    try {
      await _api.rateCustomer(jobId, {'rating': rating, if (comment != null && comment.trim().isNotEmpty) 'comment': comment.trim()});
    } catch (e) {
      throw _map(e);
    }
  }

  // ---- Earnings ---------------------------------------------------------

  Future<Cached<EarningsReport>> earnings({required String period, DateTime? date}) async {
    final dateParam = date == null ? null : '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    try {
      final json = await _api.earnings(period, dateParam) as Map<String, dynamic>;
      final data = json['data'] as Map<String, dynamic>;
      if (date == null) await _db.setValue(_earningsKey(period), jsonEncode(data));
      return Cached(EarningsReport.fromJson(data));
    } catch (e) {
      final error = _map(e);
      if (isConnectivityError(error) && date == null) {
        final cached = await _db.getValue(_earningsKey(period));
        if (cached != null) return Cached(EarningsReport.fromJson(jsonDecode(cached) as Map<String, dynamic>), fromCache: true);
      }
      throw error;
    }
  }

  Future<Map<String, dynamic>?> realtimeToken() async {
    try {
      final json = await _api.realtimeToken() as Map<String, dynamic>;
      return json['data'] as Map<String, dynamic>?;
    } catch (e) {
      final error = _map(e);
      // 503 {fallback: poll}: Firebase isn't configured server-side.
      if (error is DeliveryActionException && error.code == 'tracking_unavailable') return null;
      if (error is ServerException) return null;
      throw error;
    }
  }

  // ---- helpers ----------------------------------------------------------

  Future<DriverProfile> _profileCall(Future<dynamic> Function() call) async {
    try {
      return await _storeProfile(await call());
    } catch (e) {
      throw _map(e);
    }
  }

  Future<DriverProfile> _storeProfile(dynamic json) async {
    final data = (json as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    await _db.setValue(_meKey, jsonEncode(data));
    return DriverProfile.fromJson(data);
  }

  Future<DriverJob> _jobCall(Future<dynamic> Function() call) async {
    try {
      return await _storeJob(await call());
    } catch (e) {
      throw _map(e);
    }
  }

  Future<DriverJob> _storeJob(dynamic json) async {
    final job = DriverJob.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    await cacheActiveJob(job.isActive ? job : null);
    return job;
  }

  /// A bare 404 on these routes (no `code`) means delivery is switched off server-side.
  Object _map(Object e) {
    final error = mapDioError(e);
    if (error is NotFoundException) return const DeliveryDisabledException();
    return error;
  }
}
