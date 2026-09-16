import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../data/api/auth_api.dart';
import '../../../data/models/user.dart';
import '../../../data/repositories/auth_repository.dart';

final authApiProvider = Provider<AuthApi>((ref) {
  return AuthApi(ref.watch(dioProvider));
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    api: ref.watch(authApiProvider),
    storage: ref.watch(secureStorageProvider),
  );
});

/// The signed-in user, re-fetched whenever [authStateProvider] flips —
/// used anywhere that needs to tell "am I the buyer or the seller on this
/// order" apart without a dedicated seller-profile-id field on the user
/// resource (comparing against `order.buyer.id` is enough, since the
/// backend policy only lets one of the two parties view an order at all).
final currentUserProvider = FutureProvider.autoDispose<SokoniUser>((ref) {
  ref.watch(authStateProvider);
  return ref.watch(authRepositoryProvider).me();
});

/// Part 5 (client feedback): "an account switcher in the profile screen
/// listing signed-in accounts with avatar, name and handle." Re-fetched
/// whenever [authStateProvider] flips, same as [currentUserProvider] —
/// after a switch or an "Add account" sign-in, the list must reflect the
/// newly remembered account without a manual refresh.
final storedAccountsProvider = FutureProvider.autoDispose<List<StoredAccount>>((ref) {
  ref.watch(authStateProvider);
  return ref.watch(authRepositoryProvider).storedAccounts();
});
