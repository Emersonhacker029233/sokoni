import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/router/routes.dart';
import '../../../../data/models/seller_profile.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../providers/seller_onboarding_providers.dart';
import 'onboarding_pending_screen.dart';
import 'step1_business.dart';
import 'step2_location.dart';
import 'step3_identity.dart';
import 'step4_licence.dart';

/// 4-step seller onboarding wizard with a progress bar and saved drafts
/// (CLAUDE.md feature 4). Draft persistence is the server record itself:
/// each step PATCHes the SellerProfile created in step 1, so resuming after
/// the app is killed just means re-entering at the right step — see
/// SellerOnboardingController's docs.
class SellerOnboardingScreen extends ConsumerStatefulWidget {
  const SellerOnboardingScreen({super.key});

  @override
  ConsumerState<SellerOnboardingScreen> createState() => _SellerOnboardingScreenState();
}

class _SellerOnboardingScreenState extends ConsumerState<SellerOnboardingScreen> {
  final _pageController = PageController();
  SellerProfile? _seller;

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _goToStep(int step) {
    _pageController.animateToPage(
      step,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final draftAsync = ref.watch(sellerOnboardingProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.onboardingTitle)),
      body: draftAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: '$error',
          onRetry: () => ref.invalidate(sellerOnboardingProvider),
        ),
        data: (draft) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (_pageController.hasClients && _pageController.page?.round() != draft.step) {
              _goToStep(draft.step.clamp(0, 4));
            }
          });

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16, vertical: SokoniDimens.space8),
                child: _ProgressBar(step: draft.step),
              ),
              Expanded(
                child: PageView(
                  controller: _pageController,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    OnboardingStep1Business(
                      onSubmitted: (seller) {
                        setState(() => _seller = seller);
                        _goToStep(1);
                      },
                    ),
                    OnboardingStep2Location(
                      sellerId: draft.sellerId ?? _seller?.id,
                      onSubmitted: (seller) {
                        setState(() => _seller = seller);
                        _goToStep(2);
                      },
                    ),
                    OnboardingStep3Identity(
                      sellerId: draft.sellerId ?? _seller?.id,
                      onSubmitted: (seller) {
                        setState(() => _seller = seller);
                        _goToStep(3);
                      },
                    ),
                    OnboardingStep4Licence(
                      sellerId: draft.sellerId ?? _seller?.id,
                      onSubmitted: (seller) async {
                        await ref.read(sellerOnboardingProvider.notifier).complete();
                        if (!context.mounted) return;
                        await showOnboardingPendingScreen(context);
                        if (context.mounted) context.pushReplacement(SokoniRoutes.sell);
                      },
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ProgressBar extends StatelessWidget {
  const _ProgressBar({required this.step});

  final int step;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final labels = [
      l10n.onboardingStepBusiness,
      l10n.onboardingStepLocation,
      l10n.onboardingStepIdentity,
      l10n.onboardingStepLicence,
    ];

    return Row(
      children: [
        for (var i = 0; i < labels.length; i++)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: Column(
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 220),
                    height: 4,
                    decoration: BoxDecoration(
                      color: i <= step ? SokoniColors.sokoniYellow : outline,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    labels[i],
                    style: Theme.of(context).textTheme.bodySmall,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
