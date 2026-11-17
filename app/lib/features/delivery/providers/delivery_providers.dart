import 'dart:async';
import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/providers.dart';
import '../../../data/api/delivery_api.dart';
import '../../../data/models/delivery.dart';
import '../../../data/repositories/delivery_repository.dart';
import '../../auth/providers/auth_providers.dart';
import '../../orders/providers/order_providers.dart';

final deliveryApiProvider = Provider<DeliveryApi>((ref) => DeliveryApi(ref.watch(dioProvider)));

final deliveryRepositoryProvider = Provider<DeliveryRepository>((ref) {
  return DeliveryRepository(api: ref.watch(deliveryApiProvider));
});

/// The installed app's version name, e.g. `1.1.0`.
final appVersionProvider = FutureProvider<String>((ref) async => (await PackageInfo.fromPlatform()).version);

/// The first version with the Bolt delivery flow.
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

/// What the server lets this user see (`GET /features`). Delivery and
/// return screens appear only when this says so; a guest, anyone outside
/// the dark launch, the App Store review account, or an older server all
/// get [SokoniFeatures.off], and the app then looks exactly like the build
/// before delivery existed. Re-read on sign-in, sign-out and account switch.
final featuresProvider = FutureProvider.autoDispose<SokoniFeatures>((ref) async {
  if (!ref.watch(authStateProvider).isAuthenticated) return SokoniFeatures.off;
  try {
    await ref.watch(currentUserProvider.future); // re-run when the active account changes
  } catch (_) {
    return SokoniFeatures.off;
  }
  return ref.watch(deliveryRepositoryProvider).features();
});

/// The Bolt delivery flow's settings, or null when checkout must stay the
/// ordinary one: this build is older than 1.1, the server says delivery is
/// off for this user, or the delivery config 404s. A network failure on the
/// config is an error, not "off", so checkout can offer a retry instead of
/// silently switching flows.
final deliveryAvailabilityProvider = FutureProvider.autoDispose<DeliveryConfig?>((ref) async {
  final version = await ref.watch(appVersionProvider.future);
  if (!isVersionAtLeast(version, deliveryFlowMinVersion)) return null;
  final features = await ref.watch(featuresProvider.future);
  if (!features.deliveryEnabled) return null;
  final config = await ref.watch(deliveryRepositoryProvider).config();
  if (config == null || !isVersionAtLeast(version, config.minAppVersion)) return null;
  return config;
});

typedef QuoteRequest = ({int sellerId, double lat, double lng});

/// Bolt's delivery estimate for checkout.
final deliveryQuoteProvider = FutureProvider.autoDispose.family<DeliveryQuote, QuoteRequest>((ref, request) {
  return ref.watch(deliveryRepositoryProvider).quote(sellerId: request.sellerId, lat: request.lat, lng: request.lng);
});

/// A Bolt delivery, re-read from the server on a timer while a screen shows
/// it. There is no WebSocket server, so this polling (plus an instant
/// refresh on a matching push) is how status, payment, the Bolt driver and
/// the order's return stay current. State is null until the shop accepts.
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
  /// Bolt allows one ride-details read per 10 seconds, which the server
  /// enforces; polling faster than that would only re-read the database.
  static Duration? pollInterval(DeliveryTracking? tracking) {
    if (tracking == null) return const Duration(seconds: 8); // waiting for the shop to accept
    final returnRequest = tracking.returnInfo?.request;
    if (tracking.isTerminal) {
      // A delivered order stays live while its return is moving.
      final returnMoving = returnRequest != null && !{'refunded', 'closed'}.contains(returnRequest.status);
      return returnMoving ? const Duration(seconds: 10) : null;
    }
    if (tracking.payment?.attempt?.isPending ?? false) return const Duration(seconds: 3);
    if (tracking.isActive || tracking.status == 'pending_driver') return const Duration(seconds: 10);
    return const Duration(seconds: 8);
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
      final wasTerminal = state.value?.isTerminal ?? false;
      state = AsyncData(next);
      _schedule(next);
      // The shop just accepted, or the delivery just ended: refresh the order too.
      if ((wasNull && next != null) || (!wasTerminal && (next?.isTerminal ?? false))) ref.invalidate(orderDetailProvider(orderId));
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

  /// A return action (accept, reject, dispute, withdraw, book, refunded,
  /// pay), then a fresh read so the order screen shows the new state.
  Future<void> returnAction(int returnId, String action, [Map<String, dynamic>? body]) async {
    await ref.read(deliveryRepositoryProvider).returnAction(returnId, action, body);
    await refresh();
  }
}

final deliveryTrackingProvider = AsyncNotifierProvider.autoDispose.family<DeliveryTrackingController, DeliveryTracking?, int>(
  DeliveryTrackingController.new,
);

/// A fresh key per payment attempt; a retry of the same request reuses it.
String newIdempotencyKey() {
  final random = Random.secure();
  return List.generate(24, (_) => random.nextInt(36).toRadixString(36)).join();
}
