// Core infrastructure providers — one Dio client, one drift database, one
// secure storage instance for the whole app. Feature-level data providers
// (Phase 4+) depend on these rather than constructing their own.

import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api/device_api.dart';
import 'location/location_service.dart';
import 'network/dio_client.dart';
import 'push/push_service.dart';
import 'storage/app_database.dart';
import 'storage/secure_storage.dart';

final secureStorageProvider = Provider<SokoniSecureStorage>((ref) {
  return SokoniSecureStorage();
});

final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});

final locationServiceProvider = Provider<LocationService>((ref) {
  return LocationService(cache: ref.watch(appDatabaseProvider));
});

/// Whether a session token is present. Read at startup and updated on
/// sign-in/sign-out/401 — the router watches this to decide whether
/// auth-gated routes (order, chat, favourite; CLAUDE.md feature 4) redirect
/// to sign-in.
class AuthState {
  const AuthState({required this.isAuthenticated, required this.isLoading});

  final bool isAuthenticated;
  final bool isLoading;

  AuthState copyWith({bool? isAuthenticated, bool? isLoading}) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

class AuthStateController extends Notifier<AuthState> {
  @override
  AuthState build() {
    _loadInitial();
    return const AuthState(isAuthenticated: false, isLoading: true);
  }

  Future<void> _loadInitial() async {
    // Keystore-backed reads are usually instant but aren't guaranteed to
    // be — nothing at startup should be able to hang waiting on this, so a
    // timeout (or any other failure) just falls back to "signed out"
    // rather than leaving isLoading true forever.
    String? token;
    try {
      token = await ref.read(secureStorageProvider).readToken().timeout(const Duration(seconds: 5));
    } catch (_) {
      token = null;
    }
    state = AuthState(isAuthenticated: token != null, isLoading: false);
    if (token != null) unawaited(ref.read(pushServiceProvider).registerDevice());
  }

  void markAuthenticated() {
    state = state.copyWith(isAuthenticated: true, isLoading: false);
    unawaited(ref.read(pushServiceProvider).registerDevice());
  }

  Future<void> signOut() async {
    await ref.read(secureStorageProvider).clearSession();
    await ref.read(appDatabaseProvider).clearAll();
    state = state.copyWith(isAuthenticated: false, isLoading: false);
  }
}

final authStateProvider = NotifierProvider<AuthStateController, AuthState>(
  AuthStateController.new,
);

final dioProvider = Provider<Dio>((ref) {
  return buildDioClient(
    storage: ref.watch(secureStorageProvider),
    onUnauthenticated: () async => ref.read(authStateProvider.notifier).signOut(),
  );
});

final deviceApiProvider = Provider<DeviceApi>((ref) => DeviceApi(ref.watch(dioProvider)));

final pushServiceProvider = Provider<PushService>((ref) {
  return PushService(api: ref.watch(deviceApiProvider));
});

/// Live connectivity stream — drives the "showing saved results" banner
/// (CLAUDE.md feature 10) only. Advisory, not a gate: no repository or
/// provider in this app checks this before attempting a request — a
/// device reported as "offline" here still gets a real network attempt,
/// and only a genuine connection failure (see ApiException/mapDioError)
/// ever short-circuits anything. That split matters concretely on Android
/// 16, where connectivity_plus has been observed reporting stale or
/// contradictory results when both Wi-Fi and mobile radios are active —
/// this provider being wrong in that situation now only mis-colours a
/// banner, never blocks a request that would otherwise have succeeded.
final connectivityProvider = StreamProvider<List<ConnectivityResult>>((ref) {
  return Connectivity().onConnectivityChanged;
});

final isOnlineProvider = Provider<bool>((ref) {
  final result = ref.watch(connectivityProvider);
  return result.maybeWhen(
    // `any(!= none)` rather than `!contains(none)`: some Android versions
    // report a list containing both a real connection type and a stale
    // `none` entry when radios change state — treat "has at least one real
    // connection" as online rather than letting a spurious `none` alongside
    // it flip the banner to "offline" while a connection genuinely exists.
    data: (results) => results.any((r) => r != ConnectivityResult.none),
    orElse: () => true,
  );
});
