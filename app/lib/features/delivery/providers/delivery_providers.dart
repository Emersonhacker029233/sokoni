import 'dart:async';
import 'dart:developer' as developer;
import 'dart:math';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_database/firebase_database.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/providers.dart';
import '../../../data/api/delivery_api.dart';
import '../../../data/models/delivery.dart';
import '../../../data/repositories/delivery_repository.dart';
import '../../orders/providers/order_providers.dart';

final deliveryApiProvider = Provider<DeliveryApi>((ref) => DeliveryApi(ref.watch(dioProvider)));

final deliveryRepositoryProvider = Provider<DeliveryRepository>((ref) {
  return DeliveryRepository(api: ref.watch(deliveryApiProvider));
});

/// The installed app's version name, e.g. `1.1.0`.
final appVersionProvider = FutureProvider<String>((ref) async => (await PackageInfo.fromPlatform()).version);

/// The first version with the Sokoni delivery flow. Sokoni 1.0 builds never
/// reach this code, but the check keeps the rule explicit and testable.
const deliveryFlowMinVersion = '1.1.0';

/// `true` when [version] is at least [minimum] (dotted numbers, e.g. 1.10.0 > 1.9.3).
bool isVersionAtLeast(String version, String minimum) {
  List<int> parts(String v) => [for (final p in v.split('+').first.split('-').first.split('.')) int.tryParse(p) ?? 0];
  final a = parts(version);
  final b = parts(minimum);
  for (var i = 0; i < max(a.length, b.length); i++) {
    final x = i < a.length ? a[i] : 0;
    final y = i < b.length ? b[i] : 0;
    if (x != y) return x > y;
  }
  return true;
}

/// The Sokoni delivery flow's settings, or null when checkout must stay the
/// Sokoni 1.0 one: this build is older than 1.1, or the server has
/// DELIVERY_ENABLED off. A network failure is an error, not "off", so
/// checkout can offer a retry instead of silently switching flows.
final deliveryAvailabilityProvider = FutureProvider.autoDispose<DeliveryConfig?>((ref) async {
  final version = await ref.watch(appVersionProvider.future);
  if (!isVersionAtLeast(version, deliveryFlowMinVersion)) return null;
  final config = await ref.watch(deliveryRepositoryProvider).config();
  if (config == null || !isVersionAtLeast(version, config.minAppVersion)) return null;
  return config;
});

typedef QuoteRequest = ({int sellerId, double lat, double lng});

/// The server's delivery-fee estimate for checkout.
final deliveryQuoteProvider = FutureProvider.autoDispose.family<DeliveryQuote, QuoteRequest>((ref, request) {
  return ref.watch(deliveryRepositoryProvider).quote(sellerId: request.sellerId, lat: request.lat, lng: request.lng);
});

/// A dispatch order's delivery, re-read from the server on a timer while a
/// screen shows it. There is no WebSocket server, so this polling (plus an
/// instant refresh on a matching push) is how status, payment and the
/// rider's position stay current. State is null until the shop accepts.
class DeliveryTrackingController extends AsyncNotifier<DeliveryTracking?> {
  DeliveryTrackingController(this.orderId);

  final int orderId;
  Timer? _timer;
  bool _disposed = false;

  @override
  Future<DeliveryTracking?> build() async {
    _disposed = false;
    ref.onDispose(() {
      _disposed = true;
      _timer?.cancel();
    });
    final pushes = ref.watch(pushServiceProvider).orderUpdates.where((id) => id == orderId).listen((_) => refresh());
    ref.onDispose(pushes.cancel);

    final first = await ref.read(deliveryRepositoryProvider).tracking(orderId);
    _schedule(first);
    return first;
  }

  /// How long to wait before the next read, given what is happening now.
  static Duration? pollInterval(DeliveryTracking? tracking) {
    if (tracking == null) return const Duration(seconds: 8); // waiting for the shop to accept
    if (tracking.isTerminal) return null;
    if (tracking.payment?.attempt?.isPending ?? false) return const Duration(seconds: 3);
    if (tracking.isActive) return const Duration(seconds: 4); // the rider's position moves
    return const Duration(seconds: 6);
  }

  void _schedule(DeliveryTracking? tracking) {
    _timer?.cancel();
    final wait = pollInterval(tracking);
    if (wait == null || _disposed) return;
    _timer = Timer(wait, refresh);
  }

  /// Re-reads the delivery. A failed poll keeps the last good state on
  /// screen (a flaky connection must not flash an error every few seconds).
  Future<void> refresh() async {
    if (_disposed) return;
    try {
      final next = await ref.read(deliveryRepositoryProvider).tracking(orderId);
      if (_disposed) return;
      final wasNull = state.value == null;
      state = AsyncData(next);
      _schedule(next);
      // The shop just accepted, or the order itself changed: refresh the order too.
      if ((wasNull && next != null) || (next?.isTerminal ?? false)) ref.invalidate(orderDetailProvider(orderId));
    } catch (_) {
      if (!_disposed) _schedule(state.value);
    }
  }

  Future<void> _apply(Future<DeliveryTracking> Function() action) async {
    final next = await action();
    if (_disposed) return;
    state = AsyncData(next);
    _schedule(next);
    ref.invalidate(orderDetailProvider(orderId));
  }

  /// Sends the payment prompt. The server decides the amount and is the
  /// only one that can mark it paid; this just starts the attempt.
  Future<void> pay({required String channel, required String msisdn, required String idempotencyKey}) =>
      _apply(() => ref.read(deliveryRepositoryProvider).pay(orderId, channel: channel, msisdn: msisdn, idempotencyKey: idempotencyKey));

  Future<void> cancel({String? reason}) => _apply(() => ref.read(deliveryRepositoryProvider).cancel(orderId, reason: reason));

  Future<void> retry() => _apply(() => ref.read(deliveryRepositoryProvider).retry(orderId));

  Future<void> switchToPickup() => _apply(() => ref.read(deliveryRepositoryProvider).switchToPickup(orderId));

  Future<void> rateDriver({required int rating, String? comment}) async {
    await ref.read(deliveryRepositoryProvider).rate(orderId, rating: rating, comment: comment);
    await refresh();
  }
}

final deliveryTrackingProvider = AsyncNotifierProvider.autoDispose.family<DeliveryTrackingController, DeliveryTracking?, int>(
  DeliveryTrackingController.new,
);

/// The rider's position from Firebase Realtime Database
/// (`/tracking/{jobId}/loc`), when the server has Firebase configured.
/// Emits null (and the map falls back to the polled `driver_location`)
/// when it isn't, which is the case on staging today.
final liveDriverPositionProvider = StreamProvider.autoDispose.family<DriverPosition?, int>((ref, jobId) async* {
  final credentials = await ref.watch(deliveryRepositoryProvider).realtimeToken().catchError((Object _) => null);
  if (credentials == null) {
    yield null;
    return;
  }
  try {
    if (Firebase.apps.isEmpty) await Firebase.initializeApp().timeout(const Duration(seconds: 8));
    await FirebaseAuth.instance.signInWithCustomToken(credentials.token).timeout(const Duration(seconds: 8));
    ref.onDispose(() => FirebaseAuth.instance.signOut().catchError((Object _) {}));
    final database = FirebaseDatabase.instanceFor(app: Firebase.app(), databaseURL: credentials.databaseUrl);
    yield* database.ref('tracking/$jobId/loc').onValue.map((event) => DriverPosition.fromJson(event.snapshot.value));
  } catch (e) {
    developer.log('Live tracking unavailable; using the polled position.', name: 'Delivery', error: e);
    yield null;
  }
});

/// A fresh key per payment attempt; a retry of the same request reuses it.
String newIdempotencyKey() {
  final random = Random.secure();
  return List.generate(24, (_) => random.nextInt(36).toRadixString(36)).join();
}
