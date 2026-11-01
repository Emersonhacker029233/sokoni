import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/delivery_offer.dart';
import '../../data/models/driver_profile.dart';
import '../../shared/labels.dart';
import '../../shared/widgets/common.dart';
import '../../shared/widgets/driver_map.dart';
import '../earnings/earnings_provider.dart';
import '../profile/profile_controller.dart';
import 'go_online_flow.dart';
import 'offer_card.dart';
import 'work_controller.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  int? _accepting;
  bool _toggling = false;

  Future<void> _accept(DeliveryOffer offer) async {
    setState(() => _accepting = offer.jobId);
    try {
      await ref.read(workProvider.notifier).accept(offer);
      if (mounted) unawaited(context.push('/job'));
    } on DeliveryActionException catch (e) {
      if (mounted) showMessage(context, e.message);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _accepting = null);
    }
  }

  Future<void> _toggle(bool online) async {
    final l = AppLocalizations.of(context);
    setState(() => _toggling = true);
    try {
      if (online) {
        final ok = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            content: Text(l.goOfflineConfirm),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.cancel)),
              TextButton(onPressed: () => Navigator.pop(context, true), child: Text(l.goOffline)),
            ],
          ),
        );
        if (ok == true) await ref.read(workProvider.notifier).goOffline();
      } else {
        await goOnlineFlow(context, ref);
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _toggling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final work = ref.watch(workProvider);
    final profile = ref.watch(profileProvider).value?.profile;
    final me = work.position == null ? null : LatLng(work.position!.latitude, work.position!.longitude);
    final offer = work.offers.isEmpty ? null : work.offers.first;

    final pins = <MapPin>[
      if (offer != null) MapPin(point: LatLng(offer.shop.lat, offer.shop.lng), icon: Icons.storefront_rounded, highlight: true, label: offer.shop.name),
      if (work.job != null) MapPin(point: LatLng(work.job!.shop.lat, work.job!.shop.lng), icon: Icons.storefront_rounded, label: work.job!.shop.name),
    ];

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(child: DriverMap(pins: pins, me: me)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(SokoniDimens.space16),
              child: _TopBar(online: work.online, profile: profile),
            ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(SokoniDimens.space16),
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 320),
                  switchInCurve: Curves.easeOutBack,
                  switchOutCurve: Curves.easeInCubic,
                  transitionBuilder: (child, animation) => SlideTransition(
                    position: Tween(begin: const Offset(0, 0.25), end: Offset.zero).animate(animation),
                    child: FadeTransition(opacity: animation, child: child),
                  ),
                  child: _bottomPanel(context, work, profile, offer),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bottomPanel(BuildContext context, WorkState work, DriverProfile? profile, DeliveryOffer? offer) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);

    if (work.job != null) {
      final job = work.job!;
      return _Panel(
        key: const ValueKey('job'),
        child: InkWell(
          onTap: () => context.push('/job'),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle),
                child: const Icon(Icons.delivery_dining_rounded, color: SokoniColors.onYellow),
              ),
              const SizedBox(width: SokoniDimens.space12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.currentJob, style: theme.textTheme.bodySmall),
                    Text(jobStatusLabel(l, job.status), style: theme.textTheme.titleMedium),
                    Text(job.shop.name ?? '', style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
              FilledButton.tonal(onPressed: () => context.push('/job'), child: Text(l.openJob)),
            ],
          ),
        ),
      );
    }

    if (offer != null && work.online) {
      return OfferCard(
        key: ValueKey('offer-${offer.jobId}'),
        offer: offer,
        moreCount: work.offers.length - 1,
        accepting: _accepting == offer.jobId,
        onAccept: () => _accept(offer),
        onDecline: () => ref.read(workProvider.notifier).decline(offer),
      );
    }

    final blocked = profile != null && !profile.canGoOnline;
    return _Panel(
      key: ValueKey('idle-${work.online}-$blocked'),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (work.deliveryDisabled) ...[
            NoticeBanner(icon: Icons.info_outline_rounded, text: l.deliveryUnavailableBody),
            const SizedBox(height: SokoniDimens.space12),
          ] else if (blocked && !work.online) ...[
            _BlockedNotice(profile: profile),
            const SizedBox(height: SokoniDimens.space12),
          ],
          if (work.online) ...[
            Row(
              children: [
                const _Radar(),
                const SizedBox(width: SokoniDimens.space12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.lookingForJobs, style: theme.textTheme.titleMedium),
                      Text(l.lookingForJobsHint, style: theme.textTheme.bodySmall),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: SokoniDimens.space16),
            OutlinedButton(onPressed: _toggling ? null : () => _toggle(true), child: Text(l.goOffline)),
          ] else ...[
            Text(l.offlineHint, style: theme.textTheme.bodyMedium, textAlign: TextAlign.center),
            const SizedBox(height: SokoniDimens.space16),
            PrimaryButton(
              label: l.goOnline,
              icon: Icons.power_settings_new_rounded,
              loading: _toggling || work.busy,
              onPressed: blocked ? null : () => _toggle(false),
            ),
          ],
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(SokoniDimens.space16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(SokoniDimens.radiusSheet),
        border: Border.all(color: theme.colorScheme.outline),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: SokoniDimens.ambientShadowOpacity), blurRadius: SokoniDimens.ambientBlurRadius)],
      ),
      child: child,
    );
  }
}

class _TopBar extends ConsumerWidget {
  const _TopBar({required this.online, required this.profile});

  final bool online;
  final DriverProfile? profile;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final today = ref.watch(earningsProvider((period: 'day', date: null))).value?.value.summary;
    return _Panel(
      child: Row(
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: online ? SokoniColors.success : theme.colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.circle, size: 10, color: online ? Colors.white : theme.colorScheme.onSurface.withValues(alpha: 0.4)),
                const SizedBox(width: 6),
                Text(
                  online ? l.online : l.offline,
                  style: theme.textTheme.labelLarge?.copyWith(color: online ? Colors.white : theme.colorScheme.onSurface),
                ),
              ],
            ),
          ),
          const Spacer(),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(l.today, style: theme.textTheme.bodySmall),
              Text(DriverFormat.tzs(today?.earnings ?? 0), style: theme.textTheme.titleMedium),
              Text(l.deliveriesCount(today?.deliveredCount ?? 0), style: theme.textTheme.bodySmall),
            ],
          ),
        ],
      ),
    );
  }
}

class _BlockedNotice extends StatelessWidget {
  const _BlockedNotice({required this.profile});

  final DriverProfile profile;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final (title, body) = profile.licenceExpired
        ? (l.licenceExpiredTitle, l.licenceExpiredBody)
        : (l.vehicleUnverifiedTitle, l.vehicleUnverifiedBody);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(body, style: Theme.of(context).textTheme.bodySmall),
        if (profile.licenceExpired)
          TextButton(onPressed: () => context.go('/profile'), child: Text(l.updateDocuments)),
      ],
    );
  }
}

/// Soft expanding rings while waiting for requests.
class _Radar extends StatefulWidget {
  const _Radar();

  @override
  State<_Radar> createState() => _RadarState();
}

class _RadarState extends State<_Radar> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1800));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!MediaQuery.of(context).disableAnimations && !_c.isAnimating) _c.repeat();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 48,
      height: 48,
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) => Stack(
          alignment: Alignment.center,
          children: [
            for (final offset in const [0.0, 0.5])
              Opacity(
                opacity: (1 - ((_c.value + offset) % 1)).clamp(0, 1),
                child: Container(
                  width: 16 + 32 * ((_c.value + offset) % 1),
                  height: 16 + 32 * ((_c.value + offset) % 1),
                  decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: SokoniColors.sokoniYellow, width: 2)),
                ),
              ),
            Container(width: 14, height: 14, decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle)),
          ],
        ),
      ),
    );
  }
}
