import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/driver_job.dart';
import '../../shared/labels.dart';
import '../../shared/launch.dart';
import '../../shared/widgets/common.dart';
import '../../shared/widgets/driver_map.dart';
import '../home/work_controller.dart';
import 'job_result_view.dart';
import 'job_sheets.dart';
import 'job_steps.dart';

/// The delivery in progress: a map of the two stops and a sheet with the
/// one next step, contacts, items and the escape hatches.
class JobScreen extends ConsumerStatefulWidget {
  const JobScreen({super.key});

  @override
  ConsumerState<JobScreen> createState() => _JobScreenState();
}

class _JobScreenState extends ConsumerState<JobScreen> {
  bool _busy = false;

  WorkController get _work => ref.read(workProvider.notifier);

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _primary(DriverJob job, JobStep step) async {
    final l = AppLocalizations.of(context);
    switch (step) {
      case JobStep.confirmPickup:
        await showPickupCodeSheet(context, onSubmit: (code) => _work.advance('picked_up', pickupCode: code));
      case JobStep.completeDelivery:
        await showPinSheet(context, job: job, onSubmit: (pin, photo) => _work.advance('delivered', pin: pin, proofPhotoPath: photo));
      case JobStep.arriveAtShop || JobStep.startTrip || JobStep.arriveAtCustomer:
        await _run(() async {
          final hadNetwork = ref.read(hasNetworkProvider);
          await _work.advance(step.status!);
          final queued = ref.read(workProvider).jobFromCache;
          if (mounted && (queued || !hadNetwork)) showMessage(context, l.savedOffline);
        });
      case JobStep.none:
        break;
    }
  }

  Future<void> _callCustomer(DriverJob job) async {
    unawaited(_work.recordCallAttempt());
    if (job.customer.phone != null) await openDialer(job.customer.phone!);
  }

  Future<void> _cancel() async {
    final l = AppLocalizations.of(context);
    final note = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l.cancelJob),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(l.cancelJobConfirm),
            const SizedBox(height: 12),
            TextField(controller: note, decoration: InputDecoration(hintText: l.cancelJobReasonHint)),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.keepJob)),
          TextButton(
            style: TextButton.styleFrom(foregroundColor: SokoniColors.danger),
            onPressed: () => Navigator.pop(context, true),
            child: Text(l.cancelJob),
          ),
        ],
      ),
    );
    final reason = note.text.trim();
    note.dispose();
    if (ok != true) return;
    await _run(() async {
      await _work.cancelJob(reason.isEmpty ? null : reason);
      if (mounted) context.go('/home');
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final work = ref.watch(workProvider);
    final job = work.job;

    if (job == null) {
      final finished = work.finishedJob;
      if (finished != null) return JobResultView(job: finished);
      return Scaffold(
        appBar: AppBar(),
        body: EmptyState(
          icon: Icons.delivery_dining_outlined,
          title: l.noActiveJob,
          actionLabel: l.backToHome,
          onAction: () => context.go('/home'),
        ),
      );
    }

    final me = work.position == null ? null : LatLng(work.position!.latitude, work.position!.longitude);
    final target = targetOf(job);
    final pins = [
      MapPin(point: LatLng(job.shop.lat, job.shop.lng), icon: Icons.storefront_rounded, highlight: target == JobTarget.shop, label: job.shop.name),
      MapPin(point: LatLng(job.customer.lat, job.customer.lng), icon: Icons.home_rounded, highlight: target == JobTarget.customer, label: job.customer.name),
    ];

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(child: DriverMap(pins: pins, me: me)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(SokoniDimens.space12),
              child: Material(
                color: Theme.of(context).colorScheme.surface,
                shape: const CircleBorder(),
                child: IconButton(
                  tooltip: l.back,
                  icon: const Icon(Icons.arrow_back_rounded),
                  onPressed: () => context.canPop() ? context.pop() : context.go('/home'),
                ),
              ),
            ),
          ),
          DraggableScrollableSheet(
            initialChildSize: 0.56,
            minChildSize: 0.3,
            maxChildSize: 0.92,
            snap: true,
            builder: (context, scroll) => _JobSheet(
              job: job,
              scroll: scroll,
              busy: _busy,
              fromCache: work.jobFromCache,
              onPrimary: (step) => _primary(job, step),
              onCallCustomer: () => _callCustomer(job),
              onReport: () => showFailSheet(context, job: job, onSubmit: _work.fail),
              onCancel: _cancel,
            ),
          ),
        ],
      ),
    );
  }
}

class _JobSheet extends ConsumerWidget {
  const _JobSheet({
    required this.job,
    required this.scroll,
    required this.busy,
    required this.fromCache,
    required this.onPrimary,
    required this.onCallCustomer,
    required this.onReport,
    required this.onCancel,
  });

  final DriverJob job;
  final ScrollController scroll;
  final bool busy;
  final bool fromCache;
  final ValueChanged<JobStep> onPrimary;
  final VoidCallback onCallCustomer;
  final VoidCallback onReport;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final step = nextStep(job);
    final target = targetOf(job);
    final queued = ref.watch(outboxCountProvider).value ?? 0;

    final primaryLabel = switch (step) {
      JobStep.arriveAtShop => l.arrivedAtShop,
      JobStep.confirmPickup => l.confirmPickup,
      JobStep.startTrip => l.startTrip,
      JobStep.arriveAtCustomer => l.arrivedAtCustomer,
      JobStep.completeDelivery => l.completeDelivery,
      JobStep.none => null,
    };

    return Container(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: SokoniDimens.ambientBlurRadius)],
      ),
      child: ListView(
        controller: scroll,
        padding: const EdgeInsets.fromLTRB(SokoniDimens.space20, SokoniDimens.space8, SokoniDimens.space20, SokoniDimens.space32),
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: SokoniDimens.space12),
              decoration: BoxDecoration(color: theme.colorScheme.outline, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          if (fromCache || queued > 0) ...[
            NoticeBanner(icon: Icons.cloud_off_rounded, text: queued > 0 ? l.queuedCount(queued) : l.showingSaved),
            const SizedBox(height: SokoniDimens.space12),
          ],
          Row(
            children: [
              Expanded(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 250),
                  child: Text(jobStatusLabel(l, job.status), key: ValueKey(job.status), style: theme.textTheme.titleLarge),
                ),
              ),
              Pill(label: DriverFormat.tzs(job.driverEarnings), highlight: true),
            ],
          ),
          const SizedBox(height: 2),
          Text(l.orderCode(job.orderCode), style: theme.textTheme.bodySmall),
          const SizedBox(height: SokoniDimens.space16),
          _Progress(status: job.status),
          if (job.collectCash > 0) ...[
            const SizedBox(height: SokoniDimens.space16),
            NoticeBanner(icon: Icons.payments_outlined, text: l.collectCash(DriverFormat.tzs(job.collectCash))),
          ],
          if (job.pinLocked) ...[
            const SizedBox(height: SokoniDimens.space16),
            NoticeBanner(icon: Icons.lock_outline_rounded, text: l.pinLocked),
          ],
          const SizedBox(height: SokoniDimens.space16),
          _PlaceCard(
            title: l.shop,
            icon: Icons.storefront_rounded,
            place: job.shop,
            active: target == JobTarget.shop,
            callLabel: l.callShop,
            onCall: job.shop.phone == null ? null : () => openDialer(job.shop.phone!),
          ),
          const SizedBox(height: SokoniDimens.space12),
          _PlaceCard(
            title: l.customer,
            icon: Icons.home_rounded,
            place: job.customer,
            active: target == JobTarget.customer,
            callLabel: l.callCustomer,
            onCall: job.customer.phone == null ? null : onCallCustomer,
            footer: job.status == 'arrived' ? l.callAttempts(job.callAttempts) : null,
          ),
          if (primaryLabel != null) ...[
            const SizedBox(height: SokoniDimens.space20),
            PrimaryButton(label: primaryLabel, loading: busy, onPressed: () => onPrimary(step)),
          ],
          const SizedBox(height: SokoniDimens.space20),
          Text(l.items, style: theme.textTheme.titleMedium),
          const SizedBox(height: SokoniDimens.space8),
          for (final item in job.items)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Text('${item.qty} ×', style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(width: 8),
                  Expanded(child: Text(item.title)),
                ],
              ),
            ),
          if (job.notes != null && job.notes!.trim().isNotEmpty) ...[
            const SizedBox(height: SokoniDimens.space16),
            Text(l.notes, style: theme.textTheme.titleMedium),
            const SizedBox(height: SokoniDimens.space8),
            Text(job.notes!),
          ],
          const SizedBox(height: SokoniDimens.space24),
          if (canReportProblem(job))
            TextButton.icon(onPressed: onReport, icon: const Icon(Icons.report_problem_outlined), label: Text(l.reportProblem)),
          if (job.actions.canCancel)
            TextButton.icon(
              onPressed: busy ? null : onCancel,
              style: TextButton.styleFrom(foregroundColor: SokoniColors.danger),
              icon: const Icon(Icons.close_rounded),
              label: Text(l.cancelJob),
            ),
        ],
      ),
    );
  }
}

/// Five dots for the five driver steps, filled up to the current one.
class _Progress extends StatelessWidget {
  const _Progress({required this.status});

  final String status;

  static const _order = ['driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived'];

  @override
  Widget build(BuildContext context) {
    final index = _order.indexOf(status);
    final theme = Theme.of(context);
    return Row(
      children: [
        for (var i = 0; i < _order.length; i++) ...[
          Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              curve: Curves.easeOutCubic,
              height: 6,
              decoration: BoxDecoration(
                color: i <= index ? SokoniColors.sokoniYellow : theme.colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ),
          if (i < _order.length - 1) const SizedBox(width: 4),
        ],
      ],
    );
  }
}

class _PlaceCard extends StatelessWidget {
  const _PlaceCard({
    required this.title,
    required this.icon,
    required this.place,
    required this.active,
    required this.callLabel,
    required this.onCall,
    this.footer,
  });

  final String title;
  final IconData icon;
  final JobPlace place;
  final bool active;
  final String callLabel;
  final VoidCallback? onCall;
  final String? footer;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return AnimatedContainer(
      duration: const Duration(milliseconds: 250),
      padding: const EdgeInsets.all(SokoniDimens.space16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
        border: Border.all(color: active ? SokoniColors.sokoniYellow : theme.colorScheme.outline, width: active ? 2 : 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 20),
              const SizedBox(width: 8),
              Text(title, style: theme.textTheme.bodySmall),
            ],
          ),
          const SizedBox(height: 6),
          if (place.name != null) Text(place.name!, style: theme.textTheme.titleMedium),
          if (place.address != null && place.address!.isNotEmpty) Text(place.address!, style: theme.textTheme.bodyMedium),
          if (footer != null) ...[
            const SizedBox(height: 4),
            Text(footer!, style: theme.textTheme.bodySmall),
          ],
          const SizedBox(height: SokoniDimens.space12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => openNavigation(place.lat, place.lng),
                  icon: const Icon(Icons.navigation_outlined, size: 18),
                  label: Text(l.navigate),
                ),
              ),
              if (onCall != null) ...[
                const SizedBox(width: SokoniDimens.space8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onCall,
                    icon: const Icon(Icons.call_outlined, size: 18),
                    label: Text(callLabel, overflow: TextOverflow.ellipsis),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}
