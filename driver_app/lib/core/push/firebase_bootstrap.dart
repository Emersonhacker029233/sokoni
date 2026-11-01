import 'dart:async';
import 'dart:developer' as developer;

import 'package:firebase_core/firebase_core.dart';

/// Firebase for `tz.co.sokoni.driver` is not registered yet (BLOCKERS.md):
/// with no google-services.json / GoogleService-Info.plist, initialisation
/// throws. Everything Firebase-backed (push, live tracking) checks this
/// first and falls back to polling, so the app is fully usable without it.
abstract final class FirebaseBootstrap {
  static Future<bool>? _ready;

  static Future<bool> ensure() => _ready ??= _init();

  static Future<bool> _init() async {
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp().timeout(const Duration(seconds: 8));
      }
      return true;
    } catch (e) {
      developer.log('Firebase unavailable: push and live tracking fall back to polling.', name: 'Firebase', error: e);
      return false;
    }
  }
}
