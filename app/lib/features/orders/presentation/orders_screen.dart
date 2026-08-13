import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../auth/presentation/phone_sign_in_sheet.dart';

class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final authState = ref.watch(authStateProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.navOrders)),
      body: authState.isAuthenticated
          ? SokoniEmptyState(
              icon: Icons.receipt_long_outlined,
              title: l10n.ordersComingSoonTitle,
              message: l10n.ordersComingSoonBody,
            )
          : SokoniEmptyState(
              icon: Icons.lock_outline_rounded,
              title: l10n.profileSignInTitle,
              message: l10n.profileSignInBody,
              actionLabel: l10n.profileSignInAction,
              onAction: () => showPhoneSignInSheet(context),
            ),
    );
  }
}
