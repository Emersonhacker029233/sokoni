import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../data/models/product.dart';
import '../../discovery/providers/discovery_providers.dart';

/// Optimistic per-product favourite overrides, so tapping the heart icon
/// feels instant rather than waiting on a round trip. [Product.isFavorited]
/// (from whatever list/detail response last loaded it) is the fallback for
/// any product not yet toggled this session.
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

bool isProductFavorited(WidgetRef ref, Product product) {
  return ref.watch(favoriteOverridesProvider)[product.id] ?? product.isFavorited;
}

/// Haptic on every toggle (CLAUDE.md Part 5: "Haptic feedback on Save,
/// Follow and Order") — centralised here rather than at each of this
/// function's several call sites, so it's guaranteed consistent
/// everywhere the heart is tapped.
Future<void> toggleProductFavorite(WidgetRef ref, Product product) async {
  unawaited(HapticFeedback.lightImpact());
  final current = ref.read(favoriteOverridesProvider)[product.id] ?? product.isFavorited;
  final next = !current;
  ref.read(favoriteOverridesProvider.notifier).set(product.id, next);
  try {
    await ref.read(productRepositoryProvider).setFavorite(product.id, next);
  } catch (_) {
    ref.read(favoriteOverridesProvider.notifier).set(product.id, current);
  }
}
