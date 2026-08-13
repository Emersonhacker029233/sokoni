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
    final token = await ref.read(secureStorageProvider).readToken();
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
/// (CLAUDE.md feature 10) and gates network-only actions.
final connectivityProvider = StreamProvider<List<ConnectivityResult>>((ref) {
  return Connectivity().onConnectivityChanged;
});

final isOnlineProvider = Provider<bool>((ref) {
  final result = ref.watch(connectivityProvider);
  return result.maybeWhen(
    data: (results) => !results.contains(ConnectivityResult.none),
    orElse: () => true,
  );
});
