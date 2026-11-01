import 'dart:async';
import 'dart:developer' as developer;
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';

import '../../data/repositories/driver_repository.dart';
import 'firebase_bootstrap.dart';

/// A delivery push as the app sees it (docs/delivery/API.md, "Push data
/// payloads"): `delivery_offer`, `delivery_offer_withdrawn`, `delivery_status`.
class DriverPush {
  const DriverPush(this.type, this.data);

  final String type;
  final Map<String, dynamic> data;

  int? get jobId => int.tryParse('${data['job_id']}');

  factory DriverPush.fromMessage(RemoteMessage m) => DriverPush('${m.data['type']}', m.data);
}

/// Background handler: nothing to do. Offers shown while the app is in the
/// background arrive as notifications; tapping one opens the app, which
/// re-fetches offers.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {}

/// FCM for the driver app: registers this phone's token under
/// `/driver/devices` (kept apart from the customer app's tokens) and
/// streams incoming pushes to the work controller.
class PushService {
  PushService({required DriverRepository repository}) : _repository = repository;

  final DriverRepository _repository;
  final _messages = StreamController<DriverPush>.broadcast();
  final _subscriptions = <StreamSubscription<dynamic>>[];
  bool _started = false;

  Stream<DriverPush> get messages => _messages.stream;

  Future<void> start() async {
    if (_started) return;
    _started = true;
    if (!await FirebaseBootstrap.ensure()) return;

    try {
      final messaging = FirebaseMessaging.instance;
      FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);
      await messaging.setAutoInitEnabled(true);
      final settings = await messaging.requestPermission().timeout(const Duration(seconds: 30));
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      final token = await messaging.getToken().timeout(const Duration(seconds: 10));
      if (token != null) await _register(token);

      _subscriptions
        ..add(messaging.onTokenRefresh.listen(_register))
        ..add(FirebaseMessaging.onMessage.listen((m) => _messages.add(DriverPush.fromMessage(m))))
        ..add(FirebaseMessaging.onMessageOpenedApp.listen((m) => _messages.add(DriverPush.fromMessage(m))));

      final initial = await messaging.getInitialMessage();
      if (initial != null) _messages.add(DriverPush.fromMessage(initial));
    } catch (e) {
      developer.log('FCM registration failed; offers fall back to polling.', name: 'Push', error: e);
    }
  }

  Future<void> _register(String token) async {
    try {
      await _repository.registerDevice(fcmToken: token, platform: Platform.isIOS ? 'ios' : 'android');
    } catch (e) {
      developer.log('Device registration failed.', name: 'Push', error: e);
    }
  }

  void dispose() {
    for (final s in _subscriptions) {
      s.cancel();
    }
    _messages.close();
  }
}
