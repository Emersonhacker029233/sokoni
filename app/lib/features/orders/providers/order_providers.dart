import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../data/api/order_api.dart';
import '../../../data/models/order.dart';
import '../../../data/models/product.dart';
import '../../../data/repositories/order_repository.dart';

final orderApiProvider = Provider<OrderApi>((ref) => OrderApi(ref.watch(dioProvider)));

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return OrderRepository(api: ref.watch(orderApiProvider));
});

final myOrdersProvider = FutureProvider.autoDispose<List<Order>>((ref) async {
  final result = await ref.watch(orderRepositoryProvider).myOrders();
  return result.items;
});

final shopOrdersProvider = FutureProvider.autoDispose<List<Order>>((ref) async {
  final result = await ref.watch(orderRepositoryProvider).shopOrders();
  return result.items;
});

final orderDetailProvider = FutureProvider.autoDispose.family<Order, int>((ref, orderId) async {
  return ref.watch(orderRepositoryProvider).order(orderId);
});

/// One line in the cart — [Product] is kept (not just an id) so the cart
/// screen can render without a second fetch.
class CartLine {
  const CartLine({required this.product, required this.qty});

  final Product product;
  final int qty;

  CartLine copyWith({int? qty}) => CartLine(product: product, qty: qty ?? this.qty);

  int get lineTotal => product.price * qty;
}

/// Cart holds items from one seller at a time (CLAUDE.md feature 8) — adding
/// a product from a different seller replaces the cart rather than mixing
/// sellers, exactly like the server-side `CheckoutRequest` validator would
/// reject anyway; enforcing it client-side too avoids a round trip just to
/// find out.
class CartController extends Notifier<List<CartLine>> {
  @override
  List<CartLine> build() => const [];

  int? get sellerId => state.isEmpty ? null : state.first.product.seller?.id;

  void add(Product product, {int qty = 1}) {
    if (state.isNotEmpty && sellerId != product.seller?.id) {
      state = [CartLine(product: product, qty: qty)];
      return;
    }
    final existingIndex = state.indexWhere((line) => line.product.id == product.id);
    if (existingIndex == -1) {
      state = [...state, CartLine(product: product, qty: qty)];
      return;
    }
    state = [
      for (var i = 0; i < state.length; i++)
        if (i == existingIndex) state[i].copyWith(qty: state[i].qty + qty) else state[i],
    ];
  }

  void setQty(int productId, int qty) {
    if (qty <= 0) {
      remove(productId);
      return;
    }
    state = [
      for (final line in state)
        if (line.product.id == productId) line.copyWith(qty: qty) else line,
    ];
  }

  void remove(int productId) {
    state = state.where((line) => line.product.id != productId).toList();
  }

  void clear() => state = const [];

  int get itemCount => state.fold(0, (sum, line) => sum + line.qty);

  int get subtotal => state.fold(0, (sum, line) => sum + line.lineTotal);
}

final cartProvider = NotifierProvider<CartController, List<CartLine>>(CartController.new);
