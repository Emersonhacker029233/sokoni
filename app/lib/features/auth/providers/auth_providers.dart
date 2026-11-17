import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../data/api/auth_api.dart';
import '../../../data/models/user.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../legal/legal_content.dart';
import '../../legal/providers/terms_agreement.dart';

final authApiProvider = Provider<AuthApi>((ref) {
  return AuthApi(ref.watch(dioProvider));
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    api: ref.watch(authApiProvider),
    storage: ref.watch(secureStorageProvider),
    dio: ref.watch(dioProvider),
    // Read at request time, not watched: agreeing on the sign-in sheet
    // must not rebuild the repository halfway through a sign-in.
    agreedTermsVersion: () => ref.read(signInTermsAgreementProvider),
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

/// True when a signed-in account last agreed to an older Terms version
/// than the app ships with (Apple Guideline 1.2: Terms 1.1). The app shell
/// listens and opens the acceptance screen, so a session that was already
/// signed in before this build, or one closed on that screen, is asked at
/// the next start. Guests are never asked here; they agree on the sign-in
/// sheet instead.
final termsOutdatedProvider = FutureProvider.autoDispose<bool>((ref) async {
  if (!ref.watch(authStateProvider).isAuthenticated) return false;
  final user = await ref.watch(currentUserProvider.future);
  return user.termsVersion != sokoniTermsVersion;
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
