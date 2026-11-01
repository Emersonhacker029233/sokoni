import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/earnings.dart';
import '../../shared/labels.dart';
import '../../shared/widgets/common.dart';
import 'earnings_provider.dart';

class EarningsScreen extends ConsumerStatefulWidget {
  const EarningsScreen({super.key});

  @override
  ConsumerState<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends ConsumerState<EarningsScreen> {
  String _period = 'day';

  /// Null = the current day/week.
  DateTime? _anchor;

  EarningsQuery get _query => (period: _period, date: _anchor);

  void _shift(int direction) {
    final base = _anchor ?? DateTime.now();
    final next = base.add(Duration(days: (_period == 'week' ? 7 : 1) * direction));
    final today = DateTime.now();
    final isCurrent = _period == 'day'
        ? DateUtils.isSameDay(next, today)
        : next.isAfter(today.subtract(Duration(days: today.weekday))) && !next.isAfter(today);
    setState(() => _anchor = isCurrent || next.isAfter(today) ? null : next);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(earningsProvider(_query));

    return Scaffold(
      appBar: AppBar(title: Text(l.earningsTitle)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(earningsProvider(_query).future),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(SokoniDimens.space16, 0, SokoniDimens.space16, SokoniDimens.space32),
          children: [
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'day', label: Text(l.periodDay)),
                ButtonSegment(value: 'week', label: Text(l.periodWeek)),
              ],
              selected: {_period},
              showSelectedIcon: false,
              style: SegmentedButton.styleFrom(
                selectedBackgroundColor: SokoniColors.sokoniYellow,
                selectedForegroundColor: SokoniColors.onYellow,
              ),
              onSelectionChanged: (s) => setState(() {
                _period = s.first;
                _anchor = null;
              }),
            ),
            const SizedBox(height: SokoniDimens.space12),
            async.when(
              skipLoadingOnRefresh: true,
              data: (result) => _Report(
                report: result.value,
                fromCache: result.fromCache,
                period: _period,
                canGoForward: _anchor != null,
                onShift: _shift,
              ),
              loading: () => const _EarningsSkeleton(),
              error: (e, _) => SizedBox(height: 420, child: ErrorState(error: e, onRetry: () => ref.invalidate(earningsProvider(_query)))),
            ),
          ],
        ),
      ),
    );
  }
}

class _Report extends StatelessWidget {
  const _Report({required this.report, required this.fromCache, required this.period, required this.canGoForward, required this.onShift});

  final EarningsReport report;
  final bool fromCache;
  final String period;
  final bool canGoForward;
  final ValueChanged<int> onShift;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final s = report.summary;
    final range = period == 'day'
        ? DriverFormat.date(s.from, locale)
        : '${DriverFormat.date(s.from, locale)} – ${DriverFormat.date(s.to, locale)}';
    final pct = report.commissionPct == report.commissionPct.roundToDouble()
        ? report.commissionPct.toStringAsFixed(0)
        : report.commissionPct.toStringAsFixed(1);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (fromCache) ...[
          NoticeBanner(icon: Icons.cloud_off_rounded, text: l.showingSaved),
          const SizedBox(height: SokoniDimens.space12),
        ],
        Row(
          children: [
            IconButton(tooltip: l.previousPeriod, onPressed: () => onShift(-1), icon: const Icon(Icons.chevron_left_rounded)),
            Expanded(child: Text(range, textAlign: TextAlign.center, style: theme.textTheme.labelLarge)),
            IconButton(
              tooltip: l.nextPeriod,
              onPressed: canGoForward ? () => onShift(1) : null,
              icon: const Icon(Icons.chevron_right_rounded),
            ),
          ],
        ),
        SectionCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l.earned, style: theme.textTheme.bodySmall),
              TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: s.earnings.toDouble()),
                duration: const Duration(milliseconds: 400),
                curve: Curves.easeOutCubic,
                builder: (context, v, _) => Text(DriverFormat.tzs(v.round()), style: theme.textTheme.displayLarge),
              ),
              Text(l.deliveriesCount(s.deliveredCount), style: theme.textTheme.bodyMedium),
              if (period == 'week' && s.byDay.isNotEmpty) ...[
                const SizedBox(height: SokoniDimens.space16),
                _WeekBars(days: s.byDay, from: s.from),
              ],
              const Divider(height: 28),
              InfoRow(label: l.deliveryFees, value: DriverFormat.tzs(s.grossFees)),
              InfoRow(label: l.commissionLabel(pct), value: '− ${DriverFormat.tzs(s.commission)}'),
              if (s.cashCollected > 0) InfoRow(label: l.cashCollected, value: DriverFormat.tzs(s.cashCollected)),
            ],
          ),
        ),
        const SizedBox(height: SokoniDimens.space16),
        _BalanceCard(balance: report.balance),
        const SizedBox(height: SokoniDimens.space24),
        if (s.jobs.isEmpty)
          SizedBox(
            height: 260,
            child: EmptyState(icon: Icons.account_balance_wallet_outlined, title: l.noEarningsTitle, body: l.noEarningsBody),
          )
        else ...[
          Text(l.jobsTitle, style: theme.textTheme.titleMedium),
          const SizedBox(height: SokoniDimens.space8),
          for (final job in s.jobs) _EarningsJobTile(job: job),
        ],
        if (report.payouts.isNotEmpty) ...[
          const SizedBox(height: SokoniDimens.space24),
          Text(l.payoutsTitle, style: theme.textTheme.titleMedium),
          const SizedBox(height: SokoniDimens.space8),
          for (final p in report.payouts)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.payments_outlined),
              title: Text(DriverFormat.tzs(p.amount)),
              subtitle: Text([
                if (p.paidAt != null) DriverFormat.date(p.paidAt!, locale),
                ?p.method,
                ?p.reference,
              ].join(' · ')),
              trailing: Pill(
                label: switch (p.status) {
                  'paid' => l.payoutPaid,
                  'void' => l.payoutVoid,
                  _ => l.payoutPending,
                },
                highlight: p.status == 'paid',
              ),
            ),
        ],
      ],
    );
  }
}

class _WeekBars extends StatelessWidget {
  const _WeekBars({required this.days, required this.from});

  final List<EarningsDay> days;
  final DateTime from;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final start = DateTime(from.year, from.month, from.day);
    final byDate = {for (final d in days) d.date: d.earnings};
    final week = [
      for (var i = 0; i < 7; i++) start.add(Duration(days: i)),
    ];
    String key(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    final maxValue = week.map((d) => byDate[key(d)] ?? 0).fold<int>(1, (m, v) => v > m ? v : m);

    return SizedBox(
      height: 120,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (final d in week)
            Expanded(
              child: Semantics(
                label: '${DriverFormat.shortDay(key(d), locale)} ${DriverFormat.tzs(byDate[key(d)] ?? 0)}',
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TweenAnimationBuilder<double>(
                      tween: Tween(begin: 0, end: (byDate[key(d)] ?? 0) / maxValue),
                      duration: const Duration(milliseconds: 400),
                      curve: Curves.easeOutCubic,
                      builder: (context, v, _) => Container(
                        height: 4 + 80 * v,
                        margin: const EdgeInsets.symmetric(horizontal: 5),
                        decoration: BoxDecoration(
                          color: (byDate[key(d)] ?? 0) > 0 ? SokoniColors.sokoniYellow : theme.colorScheme.outline,
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(DriverFormat.shortDay(key(d), locale), style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance});

  final EarningsBalance balance;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final owed = balance.balance;
    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.balanceTitle, style: theme.textTheme.bodySmall),
          const SizedBox(height: 4),
          Row(
            children: [
              Expanded(
                child: Text(
                  owed > 0 ? l.balanceOwedToYou : (owed < 0 ? l.balanceYouOwe : l.balanceSettled),
                  style: theme.textTheme.titleMedium,
                ),
              ),
              Text(
                DriverFormat.tzs(owed.abs()),
                style: theme.textTheme.titleLarge?.copyWith(color: owed < 0 ? SokoniColors.danger : null),
              ),
            ],
          ),
          const Divider(height: 24),
          InfoRow(label: l.unpaidEarnings, value: DriverFormat.tzs(balance.unpaidEarnings)),
          if (balance.cashHeld > 0) InfoRow(label: l.cashHeld, value: DriverFormat.tzs(balance.cashHeld)),
        ],
      ),
    );
  }
}

class _EarningsJobTile extends StatelessWidget {
  const _EarningsJobTile({required this.job});

  final EarningsJob job;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleAvatar(
        backgroundColor: job.status == 'delivered' ? SokoniColors.sokoniYellow : theme.colorScheme.surfaceContainerHighest,
        foregroundColor: SokoniColors.onYellow,
        child: Icon(job.status == 'delivered' ? Icons.check_rounded : Icons.close_rounded, size: 20),
      ),
      title: Text(job.orderCode ?? '#${job.id}'),
      subtitle: Text([
        jobStatusLabel(l, job.status),
        if (job.settledAt != null) DriverFormat.time(job.settledAt!, locale),
        if (job.paidOut) l.paidOut,
      ].join(' · ')),
      trailing: Text(DriverFormat.tzs(job.earnedAmount), style: theme.textTheme.titleMedium),
    );
  }
}

class _EarningsSkeleton extends StatelessWidget {
  const _EarningsSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(height: 48),
        SkeletonBox(height: 190, radius: SokoniDimens.radiusCard),
        SizedBox(height: 16),
        SkeletonBox(height: 120, radius: SokoniDimens.radiusCard),
        SizedBox(height: 24),
        SkeletonBox(height: 56),
        SizedBox(height: 8),
        SkeletonBox(height: 56),
        SizedBox(height: 8),
        SkeletonBox(height: 56),
      ],
    );
  }
}
