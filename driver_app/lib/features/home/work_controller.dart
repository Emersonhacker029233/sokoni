import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../core/location/location_tracker.dart';
import '../../core/network/api_exception.dart';
import '../../core/network/dio_client.dart';
import '../../core/providers.dart';
import '../../core/push/push_service.dart';
import '../../data/models/delivery_offer.dart';
import '../../data/models/driver_job.dart';
import '../../data/models/driver_profile.dart';
import '../../data/repositories/driver_repository.dart';
import '../../data/repositories/outbox.dart';
import '../earnings/earnings_provider.dart';
import '../profile/profile_controller.dart';

/// Thrown by [WorkController.goOnline] when no GPS fix could be had.
class LocationUnavailableException implements Exception {
  const LocationUnavailableException();
}

/// Thrown when a step needs the server (pickup code, PIN) and there is no connection.
class NeedsConnectionException implements Exception {
  const NeedsConnectionException();
}

/// Mirror of `DeliveryJob::TRANSITIONS` for the steps the app may take
/// offline, so a queued step can advance the screen straight away.
const _localNext = <String, List<String>>{
  'driver_assigned': ['arrived_at_shop'],
  'arrived_at_shop': ['picked_up'],
  'picked_up': ['on_the_way', 'arrived', 'failed'],
  'on_the_way': ['arrived', 'failed'],
  'arrived': ['delivered', 'failed'],
};

class WorkState {
  const WorkState({
    this.online = false,
    this.busy = false,
    this.offers = const [],
    this.job,
    this.jobFromCache = false,
    this.finishedJob,
    this.position,
    this.liveTracking = false,
    this.deliveryDisabled = false,
  });

  final bool online;

  /// Going online/offline in progress.
  final bool busy;
  final List<DeliveryOffer> offers;
  final DriverJob? job;
  final bool jobFromCache;

  /// A job that just ended (delivered, failed, or cancelled by someone
  /// else), shown once on the result screen.
  final DriverJob? finishedJob;
  final Position? position;

  /// Writing to Firebase every few seconds (else the 10 s server heartbeat).
  final bool liveTracking;
  final bool deliveryDisabled;

  WorkState copyWith({
    bool? online,
    bool? busy,
    List<DeliveryOffer>? offers,
    DriverJob? Function()? job,
    bool? jobFromCache,
    DriverJob? Function()? finishedJob,
    Position? position,
    bool? liveTracking,
    bool? deliveryDisabled,
  }) {
    return WorkState(
      online: online ?? this.online,
      busy: busy ?? this.busy,
      offers: offers ?? this.offers,
      job: job != null ? job() : this.job,
      jobFromCache: jobFromCache ?? this.jobFromCache,
      finishedJob: finishedJob != null ? finishedJob() : this.finishedJob,
      position: position ?? this.position,
      liveTracking: liveTracking ?? this.liveTracking,
      deliveryDisabled: deliveryDisabled ?? this.deliveryDisabled,
    );
  }
}

/// The driver's shift: availability, the location heartbeat, incoming
/// offers and the job in progress.
///
/// Shared hosting has no queue worker or socket server, so everything is
/// polling backed by FCM as the fast path: offers every 10 s while online
/// and idle, the active job every 15 s (to notice a cancellation), and a
/// location heartbeat every 30 s (every 10 s on a job when the Firebase
/// live stream is unavailable, so the buyer's map still moves).
class WorkController extends Notifier<WorkState> {
  static const offersInterval = Duration(seconds: 10);
  static const jobInterval = Duration(seconds: 15);
  static const heartbeatInterval = Duration(seconds: 30);
  static const heartbeatOnJobFallback = Duration(seconds: 10);

  Timer? _offersTimer;
  Timer? _jobTimer;
  Timer? _heartbeatTimer;
  Duration? _heartbeatEvery;
  StreamSubscription<Position>? _positionSub;
  StreamSubscription<DriverPush>? _pushSub;
  StreamSubscription<OutboxRejection>? _rejectionSub;
  final _seenOfferIds = <int>{};
  bool _started = false;
  bool _liveStarting = false;
  String _notificationTitle = 'Sokoni Driver';
  String _notificationBody = '';

  final _rejections = StreamController<OutboxRejection>.broadcast();

  /// Queued steps the server refused on replay; the UI shows a snackbar.
  Stream<OutboxRejection> get rejections => _rejections.stream;

  DriverRepository get _repo => ref.read(driverRepositoryProvider);
  Outbox get _outbox => ref.read(outboxProvider);

  @override
  WorkState build() {
    ref.onDispose(_stopAll);
    ref.listen<bool>(hasNetworkProvider, (previous, next) {
      if (next && previous == false) unawaited(_onReconnect());
    });
    return const WorkState();
  }

  /// Localised text for the Android foreground-service notification.
  void setNotificationTexts(String title, String body) {
    _notificationTitle = title;
    _notificationBody = body;
  }

  /// Called once an approved driver reaches the home shell: restores an
  /// online shift or a job in progress after the app was killed.
  Future<void> start(DriverProfile profile) async {
    if (_started) return;
    _started = true;

    final push = ref.read(pushServiceProvider);
    _pushSub = push.messages.listen(_onPush);
    unawaited(push.start());
    _rejectionSub = _outbox.rejections.listen((r) {
      _rejections.add(r);
      unawaited(refreshJob());
    });

    final cachedJob = await _repo.cachedActiveJob();
    if (cachedJob != null) state = state.copyWith(job: () => cachedJob, jobFromCache: true);

    await _onReconnect();

    final tracker = ref.read(locationTrackerProvider);
    final canTrack = await tracker.access() == LocationAccess.granted;
    if (profile.isOnline && profile.canGoOnline) {
      if (canTrack) {
        state = state.copyWith(online: true);
        _syncLoops();
      } else {
        // Can't send positions without permission; stop receiving offers too.
        unawaited(_repo.setAvailability(online: false).catchError((_) => false));
      }
    } else if (state.job != null && canTrack) {
      _syncLoops();
    }
  }

  // ---- Availability -------------------------------------------------------

  Future<void> goOnline() async {
    state = state.copyWith(busy: true);
    try {
      final tracker = ref.read(locationTrackerProvider);
      final position = await tracker.current();
      if (position == null) throw const LocationUnavailableException();
      await _repo.setAvailability(online: true, lat: position.latitude, lng: position.longitude);
      state = state.copyWith(online: true, position: position);
      _syncLoops();
      unawaited(refreshOffers());
    } on DeliveryActionException {
      // vehicle_unverified / licence_expired: the profile knows why.
      unawaited(ref.read(profileProvider.notifier).refresh());
      rethrow;
    } finally {
      state = state.copyWith(busy: false);
    }
  }

  Future<void> goOffline() async {
    state = state.copyWith(busy: true);
    try {
      await _repo.setAvailability(online: false);
      state = state.copyWith(online: false, offers: const []);
      _syncLoops();
    } finally {
      state = state.copyWith(busy: false);
    }
  }

  // ---- Offers -------------------------------------------------------------

  Future<void> refreshOffers() async {
    if (!state.online || state.job != null) return;
    try {
      final offers = await _repo.offers();
      final fresh = offers.where((o) => !_seenOfferIds.contains(o.jobId)).toList();
      _seenOfferIds.addAll(offers.map((o) => o.jobId));
      state = state.copyWith(offers: offers, deliveryDisabled: false);
      if (fresh.isNotEmpty) _alert();
    } on DeliveryDisabledException {
      state = state.copyWith(deliveryDisabled: true, offers: const []);
    } on DeliveryActionException catch (e) {
      // driver_not_approved: suspended mid-shift.
      if (e.code == 'driver_not_approved' || e.code == 'not_a_driver') {
        state = state.copyWith(online: false, offers: const []);
        _syncLoops();
        unawaited(ref.read(profileProvider.notifier).refresh());
      }
    } catch (_) {
      // Keep the current list on a network blip.
    }
  }

  Future<DriverJob> accept(DeliveryOffer offer) async {
    try {
      final job = await _repo.accept(offer.jobId);
      state = state.copyWith(job: () => job, jobFromCache: false, offers: const []);
      _syncLoops();
      return job;
    } on DeliveryActionException {
      _removeOffer(offer.jobId);
      rethrow;
    }
  }

  Future<void> decline(DeliveryOffer offer) async {
    _removeOffer(offer.jobId);
    try {
      await _repo.decline(offer.jobId);
    } catch (_) {
      // Declining is a courtesy; an offer we fail to decline simply stays unanswered.
    }
  }

  void _removeOffer(int jobId) {
    state = state.copyWith(offers: state.offers.where((o) => o.jobId != jobId).toList());
  }

  // ---- The job in progress -----------------------------------------------

  Future<void> refreshJob() async {
    final previous = state.job;
    try {
      final result = await _repo.activeJob();
      final job = result.value;
      if (job == null && previous != null && !result.fromCache) {
        // It ended without us: cancelled by the buyer/seller/admin.
        DriverJob? ended;
        try {
          ended = await _repo.job(previous.id);
        } catch (_) {}
        state = state.copyWith(job: () => null, finishedJob: () => ended ?? previous.copyWith(status: 'cancelled'));
      } else {
        state = state.copyWith(job: () => job, jobFromCache: result.fromCache);
      }
      _syncLoops();
    } catch (_) {
      // Offline with no cache: keep what's on screen.
    }
  }

  /// One step forward. Steps without a code are queued when offline.
  Future<void> advance(String status, {String? pickupCode, String? pin, String? proofPhotoPath}) async {
    final job = state.job;
    if (job == null) return;

    final queueable = queueableStatuses.contains(status) && pickupCode == null && pin == null;
    // Anything queued earlier must reach the server first, or this step is out of order.
    final caughtUp = await _outbox.flush();

    if (!caughtUp) {
      if (!queueable) throw const NeedsConnectionException();
      await _queue(job, status);
      return;
    }

    try {
      final updated = await _repo.updateStatus(
        job.id,
        status,
        extra: {'pickup_code': ?pickupCode, 'pin': ?pin},
        proofPhotoPath: proofPhotoPath,
      );
      _applyJob(updated);
    } catch (e) {
      if (queueable && isConnectivityError(e)) {
        await _queue(job, status);
        return;
      }
      if (e is DeliveryActionException && (e.code == 'order_cancelled' || e.code == 'invalid_transition')) {
        unawaited(refreshJob());
      }
      if (e is DeliveryActionException && e.code == 'pin_locked') {
        state = state.copyWith(job: () => job.copyWith(pinLocked: true));
      }
      if (e is DeliveryActionException && e.code == 'wrong_pin' && e.data['attempts_left'] is int) {
        state = state.copyWith(job: () => job.copyWith(pinAttemptsLeft: e.data['attempts_left'] as int));
      }
      if (isConnectivityError(e)) throw const NeedsConnectionException();
      rethrow;
    }
  }

  Future<void> _queue(DriverJob job, String status) async {
    await _outbox.queueStatus(job.id, status);
    final optimistic = job.copyWith(
      status: status,
      actions: job.actions.copyWith(next: _localNext[status] ?? const [], canCancel: status == 'arrived_at_shop'),
      timeline: [...job.timeline, TimelineEntry(status: status, at: DateTime.now(), by: 'driver')],
    );
    await _repo.cacheActiveJob(optimistic);
    state = state.copyWith(job: () => optimistic, jobFromCache: true);
  }

  /// Counts toward the "customer unreachable" rule; queued offline.
  Future<void> recordCallAttempt() async {
    final job = state.job;
    if (job == null) return;
    try {
      if (!await _outbox.flush()) throw const NetworkException();
      final calls = await _repo.callAttempt(job.id);
      final current = state.job;
      if (current != null && current.id == job.id) {
        final updated = current.copyWith(callAttempts: calls);
        await _repo.cacheActiveJob(updated);
        state = state.copyWith(job: () => updated);
      }
    } catch (e) {
      if (!isConnectivityError(e)) return;
      await _outbox.queueCallAttempt(job.id);
      final optimistic = job.copyWith(callAttempts: job.callAttempts + 1);
      await _repo.cacheActiveJob(optimistic);
      state = state.copyWith(job: () => optimistic);
    }
  }

  Future<void> fail(String reason, String? note) async {
    final job = state.job;
    if (job == null) return;
    if (!await _outbox.flush()) throw const NeedsConnectionException();
    try {
      _applyJob(await _repo.updateStatus(job.id, 'failed', extra: {'reason': reason, 'note': ?note}));
    } catch (e) {
      if (isConnectivityError(e)) throw const NeedsConnectionException();
      rethrow;
    }
  }

  /// Driver backs out before pickup: the job goes back to dispatch without them.
  Future<void> cancelJob(String? note) async {
    final job = state.job;
    if (job == null) return;
    if (!await _outbox.flush()) throw const NeedsConnectionException();
    try {
      await _repo.updateStatus(job.id, 'cancelled', extra: {'note': ?note});
    } catch (e) {
      if (isConnectivityError(e)) throw const NeedsConnectionException();
      rethrow;
    }
    await _repo.cacheActiveJob(null);
    state = state.copyWith(job: () => null);
    _syncLoops();
    unawaited(refreshOffers());
  }

  Future<void> rateCustomer(int jobId, int rating, String? comment) => _repo.rateCustomer(jobId, rating: rating, comment: comment);

  void dismissFinished() {
    state = state.copyWith(finishedJob: () => null);
    unawaited(refreshOffers());
  }

  void _applyJob(DriverJob updated) {
    if (updated.isFinished || !updated.isActive) {
      state = state.copyWith(job: () => null, finishedJob: () => updated, jobFromCache: false);
      unawaited(ref.read(liveTrackingProvider).stop());
      unawaited(ref.read(profileProvider.notifier).refresh());
      ref.invalidate(earningsProvider);
    } else {
      state = state.copyWith(job: () => updated, jobFromCache: false);
    }
    _syncLoops();
  }

  // ---- Loops --------------------------------------------------------------

  /// Starts or stops every timer and stream to match the current state.
  void _syncLoops() {
    final onJob = state.job != null;
    final needsLocation = state.online || onJob;
    final tracker = ref.read(locationTrackerProvider);

    if (needsLocation) {
      tracker.start(notificationTitle: _notificationTitle, notificationText: _notificationBody);
      _positionSub ??= tracker.positions.listen(_onPosition);
    } else {
      unawaited(tracker.stop());
      unawaited(_positionSub?.cancel());
      _positionSub = null;
    }

    // Offers only while online and idle.
    if (state.online && !onJob) {
      _offersTimer ??= Timer.periodic(offersInterval, (_) => refreshOffers());
    } else {
      _offersTimer?.cancel();
      _offersTimer = null;
    }

    // Job polling catches cancellations if a push never arrives.
    if (onJob) {
      _jobTimer ??= Timer.periodic(jobInterval, (_) => refreshJob());
      unawaited(_ensureLiveTracking(state.job!.id));
    } else {
      _jobTimer?.cancel();
      _jobTimer = null;
      if (state.liveTracking) {
        unawaited(ref.read(liveTrackingProvider).stop());
        state = state.copyWith(liveTracking: false);
      }
    }

    final every = !needsLocation ? null : (onJob && !state.liveTracking ? heartbeatOnJobFallback : heartbeatInterval);
    if (every != _heartbeatEvery) {
      _heartbeatTimer?.cancel();
      _heartbeatTimer = every == null ? null : Timer.periodic(every, (_) => _heartbeat());
      _heartbeatEvery = every;
    }
  }

  Future<void> _ensureLiveTracking(int jobId) async {
    final live = ref.read(liveTrackingProvider);
    if (_liveStarting || (live.isLive && live.jobId == jobId)) return;
    _liveStarting = true;
    final bool ok;
    try {
      ok = await live.start(jobId);
    } finally {
      _liveStarting = false;
    }
    if (state.liveTracking != ok) {
      state = state.copyWith(liveTracking: ok);
      _syncLoops();
    }
  }

  void _onPosition(Position p) {
    state = state.copyWith(position: p);
    if (state.job != null) unawaited(ref.read(liveTrackingProvider).push(p));
  }

  Future<void> _heartbeat() async {
    final p = ref.read(locationTrackerProvider).last;
    if (p == null) return;
    try {
      await _repo.sendLocation(lat: p.latitude, lng: p.longitude, heading: p.heading, speed: p.speed, accuracy: p.accuracy);
    } catch (_) {
      // A missed heartbeat only means fewer offers for a minute.
    }
  }

  Future<void> _onReconnect() async {
    await _outbox.flush();
    await refreshJob();
    unawaited(refreshOffers());
  }

  void _onPush(DriverPush push) {
    switch (push.type) {
      case 'delivery_offer':
        unawaited(refreshOffers());
      case 'delivery_offer_withdrawn':
        if (push.jobId != null) _removeOffer(push.jobId!);
      case 'delivery_status':
        unawaited(refreshJob());
    }
  }

  void _alert() {
    unawaited(HapticFeedback.heavyImpact());
    unawaited(SystemSound.play(SystemSoundType.alert));
  }

  void _stopAll() {
    _offersTimer?.cancel();
    _jobTimer?.cancel();
    _heartbeatTimer?.cancel();
    _positionSub?.cancel();
    _pushSub?.cancel();
    _rejectionSub?.cancel();
    _rejections.close();
  }
}

final workProvider = NotifierProvider<WorkController, WorkState>(WorkController.new);
