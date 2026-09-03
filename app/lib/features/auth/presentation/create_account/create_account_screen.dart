import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../providers/create_account_providers.dart';
import 'step1_intent.dart';
import 'step2_details.dart';
import 'step3_terms.dart';
import 'step4_verify.dart';

/// The "Create an account" flow (CLAUDE.md restructure, 2026-08-25):
/// intent -> details -> terms -> verify, in that order — verifying the
/// phone number is deliberately the *last* step, not the first, so a
/// dropped connection or an interrupting phone call during the (longer,
/// for a seller) details step never wipes work already typed. Structured
/// exactly like `SellerOnboardingScreen` (a non-swipeable `PageView` driven
/// by a persisted draft) for the same reason that one is: draft
/// persistence needs a single source of truth for "which page is this",
/// not two.
class CreateAccountScreen extends ConsumerStatefulWidget {
  const CreateAccountScreen({super.key});

  @override
  ConsumerState<CreateAccountScreen> createState() => _CreateAccountScreenState();
}

class _CreateAccountScreenState extends ConsumerState<CreateAccountScreen> {
  final _pageController = PageController();

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
    final draftAsync = ref.watch(createAccountProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.authEntryCreateAccountAction)),
      body: draftAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: '$error',
          onRetry: () => ref.invalidate(createAccountProvider),
        ),
        data: (draft) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (_pageController.hasClients && _pageController.page?.round() != draft.step) {
              _goToStep(draft.step.clamp(0, 3));
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
                    CreateAccountStep1Intent(onSubmitted: () => _goToStep(1)),
                    CreateAccountStep2Details(onSubmitted: () => _goToStep(2)),
                    CreateAccountStep3Terms(onSubmitted: () => _goToStep(3)),
                    const CreateAccountStep4Verify(),
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
      l10n.createAccountStepIntent,
      l10n.createAccountStepDetails,
      l10n.createAccountStepTerms,
      l10n.createAccountStepVerify,
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
                  Text(labels[i], style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
