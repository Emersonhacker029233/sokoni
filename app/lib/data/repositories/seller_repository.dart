import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/seller_api.dart';
import '../models/paginated_result.dart';
import '../models/review.dart';
import '../models/seller_dashboard_stats.dart';
import '../models/seller_profile.dart';

class SellerRepository {
  SellerRepository({required SellerApi api, required Dio dio}) : _api = api, _dio = dio;

  final SellerApi _api;
  // Onboarding steps 3/4 are multipart file uploads. retrofit's
  // @MultiPart()/@Part() annotations add real ceremony for just two call
  // sites, so these go straight through Dio's FormData instead of SellerApi.
  final Dio _dio;

  Future<SellerProfile> byHandle(String handle) async {
    try {
      final json = await _api.show(handle);
      return SellerProfile.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Live "is this handle available" check for the "Create an account"
  /// flow's details step (CLAUDE.md restructure, 2026-08-25).
  Future<bool> checkHandleAvailable(String handle) async {
    try {
      final json = await _api.handleAvailability(handle);
      return json['available'] as bool? ?? false;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<List<SellerProfile>> browse({double? lat, double? lng, double? radiusKm}) async {
    try {
      final json = await _api.sellers({
        'lat': ?lat,
        'lng': ?lng,
        'radius_km': ?radiusKm,
      });
      final list = (json['data'] as List).cast<Map<String, dynamic>>();
      return list.map(SellerProfile.fromJson).toList();
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<(PaginatedResult<Review> page, Map<String, int> distribution)> reviews(
    String handle, {
    int page = 1,
  }) async {
    try {
      final json = await _api.reviews(handle, page) as Map<String, dynamic>;
      final result = PaginatedResult<Review>.fromJson(json, Review.fromJson);
      final distribution = <String, int>{};
      final rawDistribution = json['meta']?['distribution'] as Map<String, dynamic>?;
      rawDistribution?.forEach((rating, count) => distribution[rating] = count as int);
      return (result, distribution);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<int> startConversation({required int sellerId, int? productId}) async {
    try {
      final json = await _api.startConversation({
        'seller_id': sellerId,
        'product_id': ?productId,
      });
      return (json as Map<String, dynamic>)['data']['id'] as int;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Onboarding step 3: NIDA number + ID photo (already compressed by the
  /// caller — see NidaPhotoPicker).
  Future<SellerProfile> submitIdentity({
    required int sellerId,
    required String nidaNumber,
    required String nidaImagePath,
  }) async {
    try {
      // Laravel method-spoofing: PHP doesn't parse multipart bodies on
      // PUT/PATCH requests, so this must be a real POST with a `_method`
      // field telling the framework to treat it as PATCH.
      final response = await _dio.post(
        '/sellers/$sellerId/identity',
        data: FormData.fromMap({
          '_method': 'PATCH',
          'nida_number': nidaNumber,
          'nida_image': await MultipartFile.fromFile(nidaImagePath),
        }),
      );
      return SellerProfile.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// "Customer"/"Mteja" follow relationship (CLAUDE.md Part 3).
  Future<PaginatedResult<SellerProfile>> following({int page = 1}) async {
    try {
      final json = await _api.following(page);
      return PaginatedResult<SellerProfile>.fromJson(json as Map<String, dynamic>, SellerProfile.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> follow(String handle) async {
    try {
      await _api.follow(handle);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> unfollow(String handle) async {
    try {
      await _api.unfollow(handle);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Shop logo/avatar (CLAUDE.md Parts 3-4) — the owner's "Edit profile"
  /// action, not part of the onboarding wizard itself.
  Future<SellerProfile> uploadLogo({required int sellerId, required String logoPath}) async {
    try {
      final response = await _dio.post(
        '/sellers/$sellerId/logo',
        data: FormData.fromMap({
          '_method': 'PATCH',
          'logo': await MultipartFile.fromFile(logoPath),
        }),
      );
      return SellerProfile.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SellerProfile> updateProfile({
    required int sellerId,
    String? bio,
    String? whatsapp,
    bool? showWhatsapp,
  }) async {
    try {
      final json = await _api.updateProfile(sellerId, {
        'bio': ?bio,
        'whatsapp': ?whatsapp,
        'show_whatsapp': ?showWhatsapp,
      });
      return SellerProfile.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SellerDashboardStats> dashboard(int sellerId) async {
    try {
      final json = await _api.dashboard(sellerId);
      return SellerDashboardStats.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Onboarding step 4: business/trading licence (image or PDF). Optional —
  /// NIDA (step 3) is the actual basis of verification, so a null
  /// [licenceFilePath] simply advances the wizard with nothing attached.
  Future<SellerProfile> submitLicence({
    required int sellerId,
    String? licenceFilePath,
  }) async {
    try {
      final response = await _dio.post(
        '/sellers/$sellerId/licence',
        data: FormData.fromMap({
          '_method': 'PATCH',
          if (licenceFilePath != null) 'licence_file': await MultipartFile.fromFile(licenceFilePath),
        }),
      );
      return SellerProfile.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
