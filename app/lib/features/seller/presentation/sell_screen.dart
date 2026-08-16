import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../auth/presentation/phone_sign_in_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import 'dashboard/my_shop_dashboard.dart';

/// Centre nav tab ("+"). Browsing works without an account, but selling
/// requires one (CLAUDE.md feature 4). Once a user has started (or
/// completed) the onboarding wizard, this tab morphs from a plain
/// "start selling" prompt into a status/dashboard view — CLAUDE.md: "the
/// nav gains a 'My Shop' tab once one exists."
class SellScreen extends ConsumerWidget {
  const SellScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final authState = ref.watch(authStateProvider);

    if (!authState.isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.navSell)),
        body: SokoniEmptyState(
          icon: Icons.lock_outline_rounded,
          title: l10n.profileSignInTitle,
          message: l10n.sellSignInPrompt,
          actionLabel: l10n.profileSignInAction,
          onAction: () => showPhoneSignInSheet(context),
        ),
      );
    }

    final userAsync = ref.watch(currentUserProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.navSell)),
      body: userAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: '$error',
          onRetry: () => ref.invalidate(currentUserProvider),
        ),
        data: (user) {
          if (!user.isSeller) {
            return SokoniEmptyState(
              icon: Icons.storefront_outlined,
              title: l10n.profileStartSelling,
              message: l10n.sellSignInPrompt,
              actionLabel: l10n.profileStartSelling,
              onAction: () => context.push(SokoniRoutes.sellerOnboarding),
            );
          }
          return _MyShopStatus(status: user.sellerStatus, l10n: l10n);
        },
      ),
    );
  }
}

class _MyShopStatus extends StatelessWidget {
  const _MyShopStatus({required this.status, required this.l10n});

  final String? status;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'verified' => (l10n.myShopStatusVerified, SokoniColors.success),
      'rejected' => (l10n.myShopStatusRejected, SokoniColors.danger),
      _ => (l10n.myShopStatusPending, SokoniColors.sokoniYellow),
    };
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final onSurface = isDark ? SokoniColors.darkOnSurface : SokoniColors.sokoniBlack;

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l10n.myShopTitle, style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: SokoniDimens.space12),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: SokoniDimens.space12,
                    vertical: SokoniDimens.space8,
                  ),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.circle, size: 8, color: color),
                      const SizedBox(width: SokoniDimens.space8),
                      // onSurface, not the status accent colour — same
                      // fix as order_detail_screen's _StatusChip: the dot
                      // already carries the accent, sokoniYellow-as-text
                      // fails contrast badly. See DECISIONS.md.
                      Text(label, style: TextStyle(color: onSurface, fontWeight: FontWeight.w600)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: SokoniDimens.space24),
          const MyShopDashboard(),
        ],
      ),
    );
  }
}
