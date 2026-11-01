// App-wide infrastructure providers: one Dio, one database, one secure
// storage, one location stream. Feature controllers build on these.

import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api/auth_api.dart';
import '../data/api/driver_api.dart';
import '../data/repositories/auth_repository.dart';
import '../data/repositories/driver_repository.dart';
import '../data/repositories/outbox.dart';
import 'location/location_tracker.dart';
import 'network/dio_client.dart';
import 'push/push_service.dart';
import 'storage/app_database.dart';
import 'storage/secure_storage.dart';
import 'tracking/live_tracking.dart';

final secureStorageProvider = Provider<DriverSecureStorage>((ref) => DriverSecureStorage());

final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});

// ---- Language -----------------------------------------------------------

const supportedLanguageCodes = ['sw', 'en'];
const languageEndonyms = {'sw': 'Kiswahili', 'en': 'English'};

/// Swahili by default (docs/delivery/PLAN.md section 8); an explicit
/// choice in Profile wins and survives sign-out.
class LocaleController extends AsyncNotifier<Locale> {
  @override
  Future<Locale> build() async {
    final saved = await ref.watch(appDatabaseProvider).getValue(AppDatabase.localeKey);
    return Locale(saved != null && supportedLanguageCodes.contains(saved) ? saved : 'sw');
  }

  Future<void> set(String code) async {
    await ref.read(appDatabaseProvider).setValue(AppDatabase.localeKey, code);
    state = AsyncData(Locale(code));
  }
}

final localeProvider = AsyncNotifierProvider<LocaleController, Locale>(LocaleController.new);

// ---- Session ------------------------------------------------------------

enum AuthStatus { loading, signedIn, signedOut, unknown }

/// Whether a token is stored. [AuthStatus.unknown] means the keystore read
/// itself failed: never treated as signed out, the UI offers a retry.
class AuthController extends Notifier<AuthStatus> {
  @override
  AuthStatus build() {
    unawaited(_load());
    return AuthStatus.loading;
  }

  Future<void> _load() async {
    try {
      final token = await ref.read(secureStorageProvider).readToken().timeout(const Duration(seconds: 10));
      state = token == null ? AuthStatus.signedOut : AuthStatus.signedIn;
    } catch (_) {
      state = AuthStatus.unknown;
    }
  }

  Future<void> retry() async {
    state = AuthStatus.loading;
    await _load();
  }

  void signedIn() => state = AuthStatus.signedIn;

  /// Clears every per-account trace on this phone.
  Future<void> signedOut() async {
    await ref.read(secureStorageProvider).clearSession();
    await ref.read(appDatabaseProvider).clearAccountData();
    state = AuthStatus.signedOut;
  }
}

final authProvider = NotifierProvider<AuthController, AuthStatus>(AuthController.new);

// ---- Network ------------------------------------------------------------

final dioProvider = Provider<Dio>((ref) {
  return buildDio(
    storage: ref.watch(secureStorageProvider),
    languageCode: () => ref.read(localeProvider).value?.languageCode ?? 'sw',
    onUnauthenticated: () => ref.read(authProvider.notifier).signedOut(),
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(api: AuthApi(ref.watch(dioProvider)), storage: ref.watch(secureStorageProvider));
});

final driverRepositoryProvider = Provider<DriverRepository>((ref) {
  final dio = ref.watch(dioProvider);
  return DriverRepository(api: DriverApi(dio), dio: dio, db: ref.watch(appDatabaseProvider));
});

final outboxProvider = Provider<Outbox>((ref) {
  final outbox = Outbox(db: ref.watch(appDatabaseProvider), repository: ref.watch(driverRepositoryProvider));
  ref.onDispose(outbox.dispose);
  return outbox;
});

final outboxCountProvider = StreamProvider<int>((ref) => ref.watch(outboxProvider).watchCount());

// ---- Device services ----------------------------------------------------

final locationTrackerProvider = Provider<LocationTracker>((ref) {
  final tracker = LocationTracker();
  ref.onDispose(tracker.dispose);
  return tracker;
});

final pushServiceProvider = Provider<PushService>((ref) {
  final push = PushService(repository: ref.watch(driverRepositoryProvider));
  ref.onDispose(push.dispose);
  return push;
});

final liveTrackingProvider = Provider<LiveTracking>((ref) => LiveTracking(repository: ref.watch(driverRepositoryProvider)));

/// Advisory only, drives the offline banner and outbox flushes. Requests
/// are always attempted; only a real failure counts as offline.
final connectivityProvider = StreamProvider<List<ConnectivityResult>>((ref) => Connectivity().onConnectivityChanged);

final hasNetworkProvider = Provider<bool>((ref) {
  return ref.watch(connectivityProvider).maybeWhen(
        data: (results) => results.any((r) => r != ConnectivityResult.none),
        orElse: () => true,
      );
});
