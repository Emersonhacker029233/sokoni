import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/driver_job.dart';
import '../../shared/labels.dart';
import '../../shared/widgets/common.dart';
import '../../shared/widgets/success_check.dart';
import '../home/work_controller.dart';

/// How a job ended: the success moment and customer rating after a
/// delivery, or a plain explanation after a cancellation or failure.
class JobResultView extends ConsumerStatefulWidget {
  const JobResultView({super.key, required this.job});

  final DriverJob job;

  @override
  ConsumerState<JobResultView> createState() => _JobResultViewState();
}

class _JobResultViewState extends ConsumerState<JobResultView> {
  int _rating = 0;
  final _comment = TextEditingController();
  bool _sending = false;
  bool _rated = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  void _home() {
    ref.read(workProvider.notifier).dismissFinished();
    context.go('/home');
  }

  Future<void> _rate() async {
    setState(() => _sending = true);
    try {
      await ref.read(workProvider.notifier).rateCustomer(widget.job.id, _rating, _comment.text);
      if (!mounted) return;
      setState(() => _rated = true);
      showMessage(context, AppLocalizations.of(context).thanksForRating);
      _home();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final job = widget.job;
    final delivered = job.status == 'delivered';
    final earned = job.earnedAmount ?? (delivered ? job.driverEarnings : 0);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _home();
      },
      child: Scaffold(
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(SokoniDimens.space24),
            children: [
              const SizedBox(height: SokoniDimens.space32),
              Center(
                child: delivered
                    ? const SuccessCheck()
                    : Container(
                        width: 112,
                        height: 112,
                        decoration: BoxDecoration(color: SokoniColors.danger.withValues(alpha: 0.12), shape: BoxShape.circle),
                        child: Icon(
                          job.status == 'cancelled' ? Icons.cancel_outlined : Icons.report_problem_outlined,
                          size: 52,
                          color: SokoniColors.danger,
                        ),
                      ),
              ),
              const SizedBox(height: SokoniDimens.space24),
              Text(
                switch (job.status) {
                  'delivered' => l.deliveredTitle,
                  'cancelled' => l.jobCancelledTitle,
                  _ => l.jobFailedTitle,
                },
                style: theme.textTheme.displayLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: SokoniDimens.space12),
              Text(
                switch (job.status) {
                  'delivered' => l.deliveredBody(DriverFormat.tzs(earned)),
                  'cancelled' => l.jobCancelledBody,
                  _ => job.failureReason == 'customer_unreachable' ? failureReasonLabel(l, job.failureReason!) : l.failReturnItems,
                },
                style: theme.textTheme.bodyLarge,
                textAlign: TextAlign.center,
              ),
              if (!delivered && earned > 0) ...[
                const SizedBox(height: SokoniDimens.space8),
                Text('${l.cancellationFee}: ${DriverFormat.tzs(earned)}', style: theme.textTheme.titleMedium, textAlign: TextAlign.center),
              ],
              if (delivered && job.actions.canRate && !_rated) ...[
                const SizedBox(height: SokoniDimens.space40),
                Text(l.rateCustomer, style: theme.textTheme.titleMedium, textAlign: TextAlign.center),
                const SizedBox(height: SokoniDimens.space12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    for (var i = 1; i <= 5; i++)
                      IconButton(
                        iconSize: 40,
                        tooltip: '$i',
                        onPressed: () => setState(() => _rating = i),
                        icon: AnimatedScale(
                          scale: i <= _rating ? 1.1 : 1,
                          duration: const Duration(milliseconds: 160),
                          child: Icon(
                            i <= _rating ? Icons.star_rounded : Icons.star_outline_rounded,
                            color: i <= _rating ? SokoniColors.sokoniYellow : theme.colorScheme.outline,
                          ),
                        ),
                      ),
                  ],
                ),
                if (_rating > 0) ...[
                  const SizedBox(height: SokoniDimens.space12),
                  TextField(controller: _comment, maxLength: 500, decoration: InputDecoration(labelText: l.commentOptional)),
                  PrimaryButton(label: l.submit, loading: _sending, onPressed: _rate),
                ],
              ],
              const SizedBox(height: SokoniDimens.space24),
              OutlinedButton(onPressed: _home, child: Text(l.backToHome)),
            ],
          ),
        ),
      ),
    );
  }
}
