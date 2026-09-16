import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/storage/secure_storage.dart';

/// D-round 3 (client feedback, URGENT): the app crashed on launch with
/// `PlatformException(..., javax.crypto.BadPaddingException:
/// error:1e000065:Cipher functions:OPENSSL_internal:BAD_DECRYPT, null)` —
/// a value written under a previous flutter_secure_storage cipher
/// configuration (before `encryptedSharedPreferences: true` was turned on)
/// could no longer be decrypted, and the exception was uncaught. This
/// simulates exactly that failure at the `FlutterSecureStorage` boundary
/// [SokoniSecureStorage] wraps, on every operation — read, write, delete,
/// and the deleteAll() recovery itself — proving none of them can
/// propagate the exception any further.
///
/// The full-app version of this ("the app still reaches the feed") lives
/// in widget_test.dart, since it needs the real router/provider tree to
/// mean anything; this file proves the narrower contract that makes that
/// possible: SokoniSecureStorage itself never rethrows PlatformException.
class _CorruptedPlatformStorage extends FlutterSecureStorage {
  const _CorruptedPlatformStorage();

  static final _decryptFailure = PlatformException(
    code: 'Exception encountered',
    message:
        'javax.crypto.BadPaddingException: error:1e000065:Cipher '
        'functions:OPENSSL_internal:BAD_DECRYPT',
  );

  @override
  Future<String?> read({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;

  @override
  Future<void> write({
    required String key,
    required String? value,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;

  @override
  Future<void> delete({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;

  @override
  Future<void> deleteAll({
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;
}

void main() {
  group('SokoniSecureStorage recovers from a corrupted/undecryptable store', () {
    test('readToken() returns null rather than throwing', () async {
      final storage = SokoniSecureStorage(storage: const _CorruptedPlatformStorage());
      expect(await storage.readToken(), isNull);
    });

    test('readUserId() returns null rather than throwing', () async {
      final storage = SokoniSecureStorage(storage: const _CorruptedPlatformStorage());
      expect(await storage.readUserId(), isNull);
    });

    test('addOrUpdateAccount() completes rather than throwing', () async {
      final storage = SokoniSecureStorage(storage: const _CorruptedPlatformStorage());
      await expectLater(
        storage.addOrUpdateAccount(const StoredAccount(userId: 7, token: 'a-token', name: 'Asha')),
        completes,
      );
    });

    test('clearSession() completes rather than throwing, even though its own delete() and the recovery deleteAll() both fail', () async {
      final storage = SokoniSecureStorage(storage: const _CorruptedPlatformStorage());
      await expectLater(storage.clearSession(), completes);
    });
  });
}
