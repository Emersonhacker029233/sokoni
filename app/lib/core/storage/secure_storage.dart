import 'dart:convert';
import 'dart:developer' as developer;

import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// One signed-in account this device remembers — enough to render the
/// account switcher (avatar, name, handle) and to reattach as the active
/// bearer token without a network call. Part 5 (client feedback):
/// "Instagram-style account switching... store credentials for multiple
/// accounts in secure storage, keyed by user."
class StoredAccount {
  const StoredAccount({required this.userId, required this.token, required this.name, this.avatar, this.handle});

  final int userId;
  final String token;
  final String name;
  final String? avatar;
  final String? handle;

  Map<String, dynamic> toJson() => {
    'userId': userId,
    'token': token,
    'name': name,
    'avatar': avatar,
    'handle': handle,
  };

  factory StoredAccount.fromJson(Map<String, dynamic> json) => StoredAccount(
    userId: json['userId'] as int,
    token: json['token'] as String,
    name: json['name'] as String,
    avatar: json['avatar'] as String?,
    handle: json['handle'] as String?,
  );

  StoredAccount copyWith({String? token, String? name, String? avatar, String? handle}) => StoredAccount(
    userId: userId,
    token: token ?? this.token,
    name: name ?? this.name,
    avatar: avatar ?? this.avatar,
    handle: handle ?? this.handle,
  );
}

/// Thin wrapper around [FlutterSecureStorage] scoped to the handful of
/// secrets Sokoni persists on-device: every signed-in account this
/// device remembers, and which one is currently active.
///
/// Part 5 (client feedback): "Instagram-style account switching — a user
/// signed into one account can switch to another without signing out."
/// Every account this device has ever signed into stays in
/// [_accountsKey] (a JSON array — `flutter_secure_storage` only stores
/// strings, and this is a handful of small records, not a case for a
/// dynamic per-user key per value) until it's explicitly signed out of;
/// [_activeUserIdKey] just points at which one is currently active.
/// `readToken()`/`readUserId()` resolve through that pointer, so
/// [AuthInterceptor] and [AuthStateController] need no changes at all to
/// become multi-account-aware — switching is exactly "move the pointer",
/// nothing more, which is what makes it instant and needs no
/// re-verification.
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

  static const _accountsKey = 'sokoni.accounts';
  static const _activeUserIdKey = 'sokoni.active_user_id';

  /// The active account's bearer token, if any — resolved through
  /// [_activeUserIdKey], not a separately stored value, so switching
  /// accounts (just moving that pointer) is all [AuthInterceptor] and
  /// [AuthStateController] ever need to see.
  Future<String?> readToken() async {
    final account = await activeAccount();
    return account?.token;
  }

  Future<int?> readUserId() async {
    final raw = await _guardedRead(_activeUserIdKey);
    return raw == null ? null : int.tryParse(raw);
  }

  Future<List<StoredAccount>> readAccounts() async {
    final raw = await _guardedRead(_accountsKey);
    if (raw == null) return [];
    try {
      final list = jsonDecode(raw) as List;
      return list.map((e) => StoredAccount.fromJson(e as Map<String, dynamic>)).toList();
    } catch (e) {
      // A corrupted (not merely absent) accounts blob — same philosophy
      // as the PlatformException guards below: fail to "no accounts
      // remembered", never to a crash or a stuck app.
      developer.log('Secure storage: accounts blob failed to parse — treating as empty.', name: 'SokoniSecureStorage', error: e);
      return [];
    }
  }

  Future<StoredAccount?> activeAccount() async {
    final activeId = await readUserId();
    if (activeId == null) return null;
    final accounts = await readAccounts();
    for (final account in accounts) {
      if (account.userId == activeId) return account;
    }
    return null;
  }

  /// Adds a freshly signed-in account (or updates one already
  /// remembered — a refreshed token for the same user) and makes it the
  /// active one. Used by every real sign-in path (OTP, social, register)
  /// and by "Add account" — the two are the same operation from this
  /// storage layer's point of view.
  Future<void> addOrUpdateAccount(StoredAccount account) async {
    final accounts = await readAccounts();
    final next = [
      for (final existing in accounts)
        if (existing.userId != account.userId) existing,
      account,
    ];
    await _writeAccounts(next);
    await _guardedWrite(_activeUserIdKey, '${account.userId}');
  }

  /// Part 5 (client feedback): "Switching is instant — swap the active
  /// token, refresh providers, no re-verification." Purely a pointer
  /// move — the account must already be remembered on this device.
  Future<void> switchActiveAccount(int userId) async {
    final accounts = await readAccounts();
    if (accounts.any((a) => a.userId == userId)) {
      await _guardedWrite(_activeUserIdKey, '$userId');
    }
  }

  /// Part 5 (client feedback): "Signing out removes only the active
  /// account and returns to the next one, or to guest if it was the
  /// last." Also what a 401 (the server rejecting the active token)
  /// means now — see [AuthInterceptor], unchanged, still just calls
  /// this on a real rejection.
  Future<void> clearSession() async {
    final activeId = await readUserId();
    if (activeId == null) return;

    final remaining = (await readAccounts()).where((a) => a.userId != activeId).toList();
    await _writeAccounts(remaining);
    await _guardedDelete(_activeUserIdKey);
    if (remaining.isNotEmpty) {
      await _guardedWrite(_activeUserIdKey, '${remaining.first.userId}');
    }
  }

  Future<void> _writeAccounts(List<StoredAccount> accounts) async {
    if (accounts.isEmpty) {
      await _guardedDelete(_accountsKey);
      return;
    }
    await _guardedWrite(_accountsKey, jsonEncode(accounts.map((a) => a.toJson()).toList()));
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
