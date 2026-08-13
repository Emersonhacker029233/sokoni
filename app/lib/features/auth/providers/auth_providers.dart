import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
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
