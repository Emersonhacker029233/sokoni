import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/driver_job.dart';
import '../../shared/labels.dart';
import '../../shared/widgets/common.dart';

/// Past deliveries, 20 per page, newest first.
class HistoryState {
  const HistoryState({this.jobs = const [], this.page = 0, this.hasMore = true, this.loadingMore = false});

  final List<DriverJob> jobs;
  final int page;
  final bool hasMore;
  final bool loadingMore;
}

class HistoryController extends AsyncNotifier<HistoryState> {
  @override
  Future<HistoryState> build() async {
    final first = await ref.read(driverRepositoryProvider).pastJobs(1);
    return HistoryState(jobs: first.jobs, page: 1, hasMore: first.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.loadingMore) return;
    state = AsyncData(HistoryState(jobs: current.jobs, page: current.page, hasMore: true, loadingMore: true));
    try {
      final next = await ref.read(driverRepositoryProvider).pastJobs(current.page + 1);
      state = AsyncData(HistoryState(jobs: [...current.jobs, ...next.jobs], page: current.page + 1, hasMore: next.hasMore));
    } catch (_) {
      state = AsyncData(current);
    }
  }
}

final historyProvider = AsyncNotifierProvider.autoDispose<HistoryController, HistoryState>(HistoryController.new);

class HistoryScreen extends ConsumerWidget {
  const HistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(historyProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.jobsTitle)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(historyProvider.future),
        child: async.when(
          skipLoadingOnRefresh: true,
          loading: () => ListView(
            padding: const EdgeInsets.all(SokoniDimens.space16),
            children: [
              for (var i = 0; i < 6; i++) ...[
                const SkeletonBox(height: 76, radius: SokoniDimens.radiusCard),
                const SizedBox(height: SokoniDimens.space12),
              ],
            ],
          ),
          error: (e, _) => ListView(children: [SizedBox(height: 480, child: ErrorState(error: e, onRetry: () => ref.invalidate(historyProvider)))]),
          data: (state) {
            if (state.jobs.isEmpty) {
              return ListView(
                children: [SizedBox(height: 480, child: EmptyState(icon: Icons.receipt_long_outlined, title: l.noJobsTitle, body: l.noJobsBody))],
              );
            }
            return NotificationListener<ScrollNotification>(
              onNotification: (n) {
                if (n.metrics.extentAfter < 300) ref.read(historyProvider.notifier).loadMore();
                return false;
              },
              child: ListView.separated(
                padding: const EdgeInsets.all(SokoniDimens.space16),
                itemCount: state.jobs.length + (state.hasMore ? 1 : 0),
                separatorBuilder: (_, _) => const SizedBox(height: SokoniDimens.space12),
                itemBuilder: (context, i) {
                  if (i == state.jobs.length) {
                    return const Padding(
                      padding: EdgeInsets.all(SokoniDimens.space16),
                      child: Center(child: SizedBox.square(dimension: 24, child: CircularProgressIndicator(strokeWidth: 2.5))),
                    );
                  }
                  return _StaggeredIn(index: i, child: _JobTile(job: state.jobs[i]));
                },
              ),
            );
          },
        ),
      ),
    );
  }
}

class _JobTile extends StatelessWidget {
  const _JobTile({required this.job});

  final DriverJob job;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final delivered = job.status == 'delivered';
    return SectionCard(
      onTap: () => _showDetail(context, job),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: delivered ? SokoniColors.sokoniYellow : theme.colorScheme.surface,
              shape: BoxShape.circle,
              border: Border.all(color: theme.colorScheme.outline),
            ),
            child: Icon(delivered ? Icons.check_rounded : Icons.close_rounded, color: delivered ? SokoniColors.onYellow : null),
          ),
          const SizedBox(width: SokoniDimens.space12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(job.shop.name ?? l.orderCode(job.orderCode), style: theme.textTheme.titleMedium),
                Text(
                  [jobStatusLabel(l, job.status), if (job.createdAt != null) DriverFormat.dateTime(job.createdAt!, locale)].join(' · '),
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Text(DriverFormat.tzs(job.earnedAmount ?? 0), style: theme.textTheme.titleMedium),
        ],
      ),
    );
  }

  void _showDetail(BuildContext context, DriverJob job) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        maxChildSize: 0.9,
        builder: (context, scroll) => ListView(
          controller: scroll,
          padding: const EdgeInsets.fromLTRB(SokoniDimens.space24, 0, SokoniDimens.space24, SokoniDimens.space24),
          children: [
            Text(l.orderCode(job.orderCode), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space8),
            InfoRow(label: l.shop, value: job.shop.name ?? ''),
            InfoRow(label: l.customer, value: job.customer.name ?? ''),
            if (job.distanceKm != null) InfoRow(label: l.tripDistance(DriverFormat.km(job.distanceKm!)), value: ''),
            InfoRow(label: l.earned, value: DriverFormat.tzs(job.earnedAmount ?? 0), bold: true),
            const SizedBox(height: SokoniDimens.space16),
            Text(l.jobTimeline, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: SokoniDimens.space8),
            for (final e in job.timeline)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.circle, size: 10, color: SokoniColors.sokoniYellow),
                title: Text(jobStatusLabel(l, e.status ?? '')),
                trailing: e.at == null ? null : Text(DriverFormat.time(e.at!, locale)),
              ),
          ],
        ),
      ),
    );
  }
}

/// Fade and rise, 40 ms apart, capped at 8 items (the customer app's rule).
class _StaggeredIn extends StatelessWidget {
  const _StaggeredIn({required this.index, required this.child});

  final int index;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (index >= 8 || MediaQuery.of(context).disableAnimations) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 280 + 40 * index),
      curve: Interval(index * 40 / (280 + 40 * index), 1, curve: Curves.easeOutCubic),
      builder: (context, v, child) => Opacity(opacity: v, child: Transform.translate(offset: Offset(0, 16 * (1 - v)), child: child)),
      child: child,
    );
  }
}
