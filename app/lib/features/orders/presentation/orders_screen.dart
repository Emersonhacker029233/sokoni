import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/order.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../auth/presentation/phone_sign_in_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import '../providers/order_providers.dart';

class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final authState = ref.watch(authStateProvider);

    if (!authState.isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.navOrders)),
        body: SokoniEmptyState(
          icon: Icons.lock_outline_rounded,
          title: l10n.profileSignInTitle,
          message: l10n.profileSignInBody,
          actionLabel: l10n.profileSignInAction,
          onAction: () => showPhoneSignInSheet(context),
        ),
      );
    }

    final userAsync = ref.watch(currentUserProvider);
    final isSeller = userAsync.maybeWhen(data: (user) => user.isSeller, orElse: () => false);

    if (!isSeller) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.navOrders)),
        body: const _OrderList(shopView: false),
      );
    }

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.navOrders),
          bottom: TabBar(
            tabs: [Tab(text: l10n.ordersMyOrdersTab), Tab(text: l10n.ordersShopOrdersTab)],
          ),
        ),
        body: const TabBarView(
          children: [_OrderList(shopView: false), _OrderList(shopView: true)],
        ),
      ),
    );
  }
}

class _OrderList extends ConsumerWidget {
  const _OrderList({required this.shopView});

  final bool shopView;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final ordersAsync = ref.watch(shopView ? shopOrdersProvider : myOrdersProvider);

    return ordersAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: error is ApiException ? error.message : '$error',
        onRetry: () => ref.invalidate(shopView ? shopOrdersProvider : myOrdersProvider),
      ),
      data: (orders) {
        if (orders.isEmpty) {
          return SokoniEmptyState(
            icon: Icons.receipt_long_outlined,
            title: l10n.ordersEmptyTitle,
            message: l10n.ordersEmptyBody,
          );
        }
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(shopView ? shopOrdersProvider : myOrdersProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(SokoniDimens.space16),
            itemCount: orders.length,
            separatorBuilder: (_, _) => const Divider(),
            itemBuilder: (context, index) => _OrderRow(order: orders[index], shopView: shopView),
          ),
        );
      },
    );
  }
}

class _OrderRow extends StatelessWidget {
  const _OrderRow({required this.order, required this.shopView});

  final Order order;
  final bool shopView;

  @override
  Widget build(BuildContext context) {
    final counterparty = shopView ? order.buyer?.name : order.seller?.shopName;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(order.code),
      subtitle: Text('${counterparty ?? ''} · ${SokoniFormat.tzs(order.total)}'),
      trailing: Text(order.status),
      onTap: () => context.push(SokoniRoutes.orderDetail(order.id)),
    );
  }
}
