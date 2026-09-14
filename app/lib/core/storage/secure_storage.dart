import 'dart:developer' as developer;

import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Thin wrapper around [FlutterSecureStorage] scoped to the handful of
/// secrets Sokoni persists on-device: the Sanctum bearer token and the
/// signed-in user's id (used to key the drift cache per-account).
///
/// `encryptedSharedPreferences: true` routes storage through AndroidX
/// Security's `EncryptedSharedPreferences`/`MasterKey` rather than this
/// plugin's own legacy RSA_ECB_PKCS1Padding-wrapped-AES cipher (the
/// previous default) — Google's actively maintained, better-audited
/// implementation.
///
/// That switch turned out NOT to be the transparent migration its own
/// docblock here used to claim: on a real device, a value written under
/// the old cipher surfaced as a `PlatformException` —
/// `BadPaddingException: BAD_DECRYPT` — the first time anything tried to
/// read it back under the new configuration, and it reached
/// [AuthInterceptor]'s `onRequest` (called on literally the first API
/// request the app makes) unguarded, killing the app before it ever
/// painted a frame.
///
/// Fixed at both the layer that actually threw and the layer that let it
/// through:
///
/// - `resetOnError: true` is the plugin's own native-side answer to
///   exactly this (its own doc comment on the Android implementation says
///   "will prevent fatal errors regarding an unknown key"): read from
///   `FlutterSecureStoragePlugin.java`'s `MethodRunner.run()` — every
///   native call already runs inside a try/catch, and with this on, any
///   exception there (this one included) makes it call `deleteAll()` and
///   report success instead of surfacing the exception to Dart at all.
///   Without it, the same catch block instead delivers the exception to
///   Dart as a normal, catchable `PlatformException` — which is what was
///   missing a catch here.
/// - Every read, write and delete below is also wrapped in Dart, so a
///   corrupted/undecryptable store (this one, or any other platform's
///   equivalent failure — iOS Keychain, etc., where the native
///   self-healing above doesn't apply) can never reach a caller
///   unguarded either: on any [PlatformException], it's treated as
///   unrecoverable, logged, wiped with `deleteAll()` (itself guarded the
///   same way, in case wiping is what's actually broken), and the call
///   returns/no-ops as if the value was never there. A lost session is an
///   acceptable outcome here; an app that cannot open is not. See
///   DECISIONS.md.
///
/// This does NOT fix the separate, real durability gap confirmed in the
/// plugin's own source (both 9.2.4, pinned here, and 11.0.0): every write
/// path calls `SharedPreferences.Editor.apply()`, not `.commit()`, so a
/// value can be considered "written" in Dart before it has actually
/// reached disk. Android's OS flushes pending `apply()` writes during a
/// graceful `onStop()`, which covers most ways of closing an app, but not
/// an abrupt process kill. Fixing that fully would require patching or
/// replacing the plugin's native Android layer — out of scope here; see
/// DECISIONS.md.
class SokoniSecureStorage {
  SokoniSecureStorage({FlutterSecureStorage? storage})
    : _storage =
          storage ??
          const FlutterSecureStorage(
            aOptions: AndroidOptions(encryptedSharedPreferences: true, resetOnError: true),
          );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'sokoni.auth_token';
  static const _userIdKey = 'sokoni.user_id';

  Future<String?> readToken() => _guardedRead(_tokenKey);

  Future<void> writeToken(String token) => _guardedWrite(_tokenKey, token);

  Future<int?> readUserId() async {
    final raw = await _guardedRead(_userIdKey);
    return raw == null ? null : int.tryParse(raw);
  }

  Future<void> writeUserId(int id) => _guardedWrite(_userIdKey, '$id');

  /// Clears the session on logout/401 — does not touch any other secrets.
  Future<void> clearSession() async {
    await _guardedDelete(_tokenKey);
    await _guardedDelete(_userIdKey);
  }

  Future<String?> _guardedRead(String key) async {
    try {
      return await _storage.read(key: key);
    } on PlatformException catch (e) {
      await _recoverFromCorruption('read', key, e);
      return null;
    }
  }

  Future<void> _guardedWrite(String key, String value) async {
    try {
      await _storage.write(key: key, value: value);
    } on PlatformException catch (e) {
      await _recoverFromCorruption('write', key, e);
    }
  }

  Future<void> _guardedDelete(String key) async {
    try {
      await _storage.delete(key: key);
    } on PlatformException catch (e) {
      await _recoverFromCorruption('delete', key, e);
    }
  }

  /// The store's underlying cipher/keystore is unusable for at least this
  /// key — most commonly a value written under a since-changed encryption
  /// configuration that can no longer be decrypted. There is no safe way
  /// to recover an individual key without native-level cipher access this
  /// plugin doesn't expose, so this wipes everything: a lost session is
  /// recoverable (sign in again); an app that can't get past this
  /// exception is not.
  Future<void> _recoverFromCorruption(String op, String key, PlatformException e) async {
    developer.log(
      'Secure storage $op("$key") failed (${e.code}: ${e.message}) — wiping store, continuing signed out.',
      name: 'SokoniSecureStorage',
      error: e,
    );
    try {
      await _storage.deleteAll();
    } on PlatformException catch (deleteAllError) {
      // Wiping itself failed — the corruption may be at the keystore/
      // master-key level rather than per-value. Nothing more can be done
      // from here; swallow it and let the app continue as signed out
      // regardless, rather than letting a failed cleanup attempt become
      // its own uncaught exception.
      developer.log(
        'Secure storage deleteAll() also failed while recovering — continuing anyway.',
        name: 'SokoniSecureStorage',
        error: deleteAllError,
      );
    }
  }
}
