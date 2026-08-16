import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../providers/auth_providers.dart';

enum _Intent { buy, sell, later }

/// One-question intent screen shown once, right after a brand-new
/// account's first sign-in (CLAUDE.md Part 2 item 2). Presentation only —
/// every option lands the same one-account-with-optional-seller-profile
/// model; "sell" just fast-forwards into the existing onboarding wizard
/// instead of leaving the buyer to find "Start selling" themselves.
///
/// Reached via `context.go`, which replaced the whole stack, so there's
/// nothing to pop back to — every option below also uses `context.go`
/// rather than `push`, so this screen never lingers in the back stack
/// once the choice is made (it must never reappear).
class AccountIntentScreen extends ConsumerStatefulWidget {
  const AccountIntentScreen({super.key});

  @override
  ConsumerState<AccountIntentScreen> createState() => _AccountIntentScreenState();
}

class _AccountIntentScreenState extends ConsumerState<AccountIntentScreen> {
  _Intent? _submitting;
  String? _error;

  Future<void> _choose(_Intent intent) async {
    setState(() {
      _submitting = intent;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).submitIntent(intent.name);
      if (!mounted) return;
      context.go(intent == _Intent.sell ? SokoniRoutes.sellerOnboarding : SokoniRoutes.home);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(SokoniDimens.space20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: SokoniDimens.space24),
              Text(l10n.accountIntentTitle, style: textTheme.displaySmall),
              const SizedBox(height: SokoniDimens.space8),
              Text(l10n.accountIntentSubtitle, style: textTheme.bodyMedium),
              const SizedBox(height: SokoniDimens.space32),
              _IntentOption(
                icon: Icons.shopping_bag_outlined,
                title: l10n.accountIntentBuyTitle,
                subtitle: l10n.accountIntentBuySubtitle,
                loading: _submitting == _Intent.buy,
                enabled: _submitting == null,
                onTap: () => _choose(_Intent.buy),
              ),
              const SizedBox(height: SokoniDimens.space12),
              _IntentOption(
                icon: Icons.storefront_outlined,
                title: l10n.accountIntentSellTitle,
                subtitle: l10n.accountIntentSellSubtitle,
                loading: _submitting == _Intent.sell,
                enabled: _submitting == null,
                onTap: () => _choose(_Intent.sell),
              ),
              const SizedBox(height: SokoniDimens.space12),
              _IntentOption(
                icon: Icons.schedule_outlined,
                title: l10n.accountIntentLaterTitle,
                subtitle: l10n.accountIntentLaterSubtitle,
                loading: _submitting == _Intent.later,
                enabled: _submitting == null,
                onTap: () => _choose(_Intent.later),
              ),
              if (_error != null) ...[
                const SizedBox(height: SokoniDimens.space16),
                Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _IntentOption extends StatelessWidget {
  const _IntentOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.loading,
    required this.enabled,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool loading;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final textTheme = Theme.of(context).textTheme;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
      child: InkWell(
        borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
        onTap: enabled ? onTap : null,
        child: Container(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
            border: Border.all(color: outline),
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  color: SokoniColors.sokoniYellow,
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Icon(icon, size: 24, color: SokoniColors.onYellow),
              ),
              const SizedBox(width: SokoniDimens.space16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: textTheme.titleMedium),
                    const SizedBox(height: SokoniDimens.space4),
                    Text(subtitle, style: textTheme.bodySmall),
                  ],
                ),
              ),
              if (loading)
                const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              else
                const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
