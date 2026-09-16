// Core infrastructure providers — one Dio client, one drift database, one
// secure storage instance for the whole app. Feature-level data providers
// (Phase 4+) depend on these rather than constructing their own.

import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api/device_api.dart';
import '../main.dart' show restartApp;
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

/// - [loading]: the initial secure-storage read hasn't resolved yet.
/// - [authenticated] / [unauthenticated]: the read completed and
///   definitively found (or didn't find) a token.
/// - [unknown]: the read could not be completed at all (a genuinely hung
///   native call past the safety-net timeout, or a thrown exception) — NOT
///   the same as "signed out". A session that exists on disk must never be
///   discarded just because this one attempt to read it failed; the correct
///   response is to let the caller retry, never to silently drop to guest.
///   See DECISIONS.md: an earlier version of this controller collapsed a
///   slow/failed read straight into "signed out", which is indistinguishable
///   from a genuine session loss to both the user and the rest of the app.
enum AuthStatus { loading, authenticated, unauthenticated, unknown }

/// Whether a session token is present. Read at startup and updated on
/// sign-in/sign-out/401 — the router watches this to decide whether
/// auth-gated routes (order, chat, favourite; CLAUDE.md feature 4) redirect
/// to sign-in.
class AuthState {
  const AuthState(this.status);

  final AuthStatus status;

  bool get isAuthenticated => status == AuthStatus.authenticated;
  bool get isLoading => status == AuthStatus.loading;
  bool get isUnknown => status == AuthStatus.unknown;
}

class AuthStateController extends Notifier<AuthState> {
  /// Generous on purpose: this is a local, offline keystore read with
  /// nothing downstream waiting on it (the splash screen navigates on its
  /// own fixed timer regardless of auth state — see splash_screen.dart), so
  /// there is no reason to cut it short. This exists only as a last-resort
  /// circuit breaker against a genuinely hung native call, not as a normal
  /// code path — see the class docs on [AuthStatus.unknown] for why its
  /// failure must never be treated as "signed out". Matches the order of
  /// magnitude of this app's other bounded startup calls (PushService's
  /// per-step timeout and LocationService's position fetch are both 8s).
  static const _safetyNetTimeout = Duration(seconds: 10);

  @override
  AuthState build() {
    _loadInitial();
    return const AuthState(AuthStatus.loading);
  }

  Future<void> _loadInitial() async {
    state = const AuthState(AuthStatus.loading);

    String? token;
    try {
      token = await ref.read(secureStorageProvider).readToken().timeout(_safetyNetTimeout);
    } catch (_) {
      // Could not determine the real state — leave it undetermined rather
      // than asserting "signed out" and forcing a real session to look lost.
      state = const AuthState(AuthStatus.unknown);
      return;
    }

    state = AuthState(token != null ? AuthStatus.authenticated : AuthStatus.unauthenticated);
    if (token != null) unawaited(ref.read(pushServiceProvider).registerDevice());
  }

  /// Re-runs the initial load — the only way out of [AuthStatus.unknown],
  /// exposed so the UI can offer "try again" rather than silently treating
  /// an undetermined session as a guest one.
  Future<void> retry() => _loadInitial();

  void markAuthenticated() {
    state = const AuthState(AuthStatus.authenticated);
    unawaited(ref.read(pushServiceProvider).registerDevice());
  }

  /// Part 5 (client feedback): "Signing out removes only the active
  /// account and returns to the next one, or to guest if it was the
  /// last." `clearSession()` already does the "which account is next"
  /// part (see SokoniSecureStorage); this only has to make the rest of
  /// the app agree, which — since ANY leftover per-account provider
  /// state (the cart, drafts, unread counts) is exactly the leakage
  /// this feature was built to prevent — means a full restart, not just
  /// flipping `state`. `restartApp()`'s own fresh AuthStateController
  /// re-reads storage in `_loadInitial()` and lands on whichever
  /// outcome is now correct (the next account, or guest) — this method
  /// never needs to know which itself.
  Future<void> signOut() async {
    await ref.read(secureStorageProvider).clearSession();
    await ref.read(appDatabaseProvider).clearAll();
    restartApp();
  }

  /// Part 5 (client feedback): "Switching is instant — swap the active
  /// token, refresh providers, no re-verification." Goes straight to
  /// [secureStorageProvider] rather than through AuthRepository, which
  /// already depends on this file (core/) — reaching back the other way
  /// would be a circular import for no real benefit, since this is
  /// exactly the same storage instance either way.
  Future<void> switchAccount(int userId) async {
    await ref.read(secureStorageProvider).switchActiveAccount(userId);
    restartApp();
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

/// Per-account scoping for [AppDatabase]'s generic key/value cache table.
///
/// Part 5 (client feedback): "Be careful with anything holding per-user
/// state — the cart, drafts, cached feed, unread counts — all of it must
/// switch with the account rather than leaking between them." The
/// full-ProviderScope restart in [AuthStateController.switchAccount] wipes
/// every in-memory provider (the cart included), but does nothing for
/// state that's persisted to disk under a fixed key — search history,
/// the seller-onboarding draft, and per-shop "seen" marks all go through
/// this same key/value table, and without scoping, a freshly-restarted
/// provider for the newly active account would read the PREVIOUS
/// account's cached value straight back out.
///
/// Falls back to a fixed `'guest'` scope when signed out, matching this
/// app's browse-without-an-account model (CLAUDE.md feature 4) — a
/// guest's search history is still meaningfully "theirs" until they sign
/// in, and reusing one fixed scope for every signed-out visitor is no
/// worse than this app's pre-Part-5 behaviour (there was only ever one
/// account at a time before).
Future<String> scopedCacheKey(Ref ref, String key) async {
  final userId = await ref.watch(secureStorageProvider).readUserId();
  return userId == null ? 'guest:$key' : 'u$userId:$key';
}

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
