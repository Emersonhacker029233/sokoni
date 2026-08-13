import 'dart:developer' as developer;
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import '../../data/api/device_api.dart';

/// FCM device registration (CLAUDE.md feature 8: push on new order/status
/// change — the server side is fully real, see `LogPushNotifier`; this is
/// the client half that gets a real token to a real device row).
///
/// BLOCKERS.md item 1: no Firebase project exists yet, so there is no
/// `google-services.json`/`GoogleService-Info.plist` for `Firebase.
/// initializeApp()` to read. Every call in this class is the real,
/// production implementation — nothing here is a stub — but the whole
/// class deliberately fails soft: with no config files, native
/// initialization throws immediately, caught here so the app keeps
/// working as a fully-functional in-app-only experience (chat's 5s
/// polling still works with no push at all) rather than crashing.
/// Dropping the two config files in is the only thing needed to light
/// this up for real.
class PushService {
  PushService({required DeviceApi api}) : _api = api;

  final DeviceApi _api;

  Future<void> registerDevice() async {
    try {
      await Firebase.initializeApp();
    } catch (e) {
      developer.log('Firebase unavailable (no config files yet) — push disabled.', name: 'PushService', error: e);
      return;
    }

    try {
      final messaging = FirebaseMessaging.instance;
      final settings = await messaging.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      final token = await messaging.getToken();
      if (token != null) await _register(token);

      messaging.onTokenRefresh.listen(_register);
    } catch (e) {
      developer.log('FCM registration failed.', name: 'PushService', error: e);
    }
  }

  Future<void> _register(String token) async {
    try {
      await _api.register({
        'fcm_token': token,
        'platform': Platform.isIOS ? 'ios' : 'android',
      });
    } catch (e) {
      developer.log('Device registration request failed.', name: 'PushService', error: e);
    }
  }
}
