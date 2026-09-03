import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../discovery/providers/discovery_providers.dart';

/// Optimistic per-product favourite overrides, so tapping the heart icon
/// feels instant rather than waiting on a round trip. A product's own
/// `isFavorited` (from whatever list/detail response last loaded it) is the
/// fallback for any product not yet toggled this session.
class FavoriteOverrides extends Notifier<Map<int, bool>> {
  @override
  Map<int, bool> build() => {};

  void set(int productId, bool value) {
    state = {...state, productId: value};
  }
}

final favoriteOverridesProvider = NotifierProvider<FavoriteOverrides, Map<int, bool>>(
  FavoriteOverrides.new,
);

/// [knownFavorited] is the server's last-known value for this product
/// (`Product.isFavorited`) — deliberately a plain bool rather than a whole
/// `Product`, so a lighter embed with no favourite state of its own (e.g.
/// `ProductSummary`, all the Showcase feed has) can still participate by
/// passing `false` and letting the optimistic override take over from the
/// first interaction this session.
bool isProductFavorited(WidgetRef ref, {required int productId, bool knownFavorited = false}) {
  return ref.watch(favoriteOverridesProvider)[productId] ?? knownFavorited;
}

/// Haptic on every toggle (CLAUDE.md Part 5: "Haptic feedback on Save,
/// Follow and Order") — centralised here rather than at each of this
/// function's several call sites, so it's guaranteed consistent
/// everywhere the heart is tapped.
///
/// [onUnauthenticated] and [onFailed] are deliberately separate: a guest
/// tapping the heart is not a failure (see DECISIONS.md — this used to
/// show the same "check your connection" toast a guest got for a genuine
/// network error, which is both wrong and unkind) and must never be
/// reported as one; [onFailed] is for every other real failure and
/// receives the actual `ApiException.message` rather than a fixed string,
/// so a guest never sees connection-flavoured text for a 401 and a real
/// server error isn't misreported as connectivity either.
Future<void> toggleProductFavorite(
  WidgetRef ref, {
  required int productId,
  required bool knownFavorited,
  required VoidCallback onUnauthenticated,
  required void Function(String message) onFailed,
}) async {
  unawaited(HapticFeedback.lightImpact());
  final current = ref.read(favoriteOverridesProvider)[productId] ?? knownFavorited;
  final next = !current;
  ref.read(favoriteOverridesProvider.notifier).set(productId, next);
  try {
    await ref.read(productRepositoryProvider).setFavorite(productId, next);
  } catch (e) {
    ref.read(favoriteOverridesProvider.notifier).set(productId, current);
    if (e is UnauthenticatedException) {
      onUnauthenticated();
    } else {
      onFailed(e is ApiException ? e.message : '$e');
    }
  }
}

/// Idempotent "save" for the Instagram-style double-tap gesture
/// (`DoubleTapToSave`) — deliberately never a toggle, unlike
/// [toggleProductFavorite] above. Double-tapping an item that's already
/// saved must not unsave it (it just replays the heart animation and
/// leaves the item saved, matching Instagram and avoiding an accidental
/// second tap undoing the first), so an already-known-saved product is a
/// no-op: no state change, no redundant API call. Haptic and the
/// heart/spring animations are the caller's (the gesture widget's)
/// responsibility, not this function's — this only owns the optimistic
/// state + persistence + quiet revert-on-failure.
///
/// See [toggleProductFavorite]'s docs for why [onUnauthenticated] and
/// [onFailed] are separate — a guest double-tapping to save gets an
/// invitation to sign in, never an error toast.
Future<void> saveProductViaDoubleTap(
  WidgetRef ref, {
  required int productId,
  required bool knownFavorited,
  required VoidCallback onUnauthenticated,
  required void Function(String message) onFailed,
}) async {
  final current = ref.read(favoriteOverridesProvider)[productId] ?? knownFavorited;
  if (current) return;

  ref.read(favoriteOverridesProvider.notifier).set(productId, true);
  try {
    await ref.read(productRepositoryProvider).setFavorite(productId, true);
  } catch (e) {
    ref.read(favoriteOverridesProvider.notifier).set(productId, current);
    if (e is UnauthenticatedException) {
      onUnauthenticated();
    } else {
      onFailed(e is ApiException ? e.message : '$e');
    }
  }
}
