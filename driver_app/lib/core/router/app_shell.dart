import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/repositories/outbox.dart';
import '../../features/home/work_controller.dart';
import '../../features/profile/profile_controller.dart';
import '../../shared/widgets/common.dart';
import '../l10n/gen/app_localizations.dart';
import '../providers.dart';
import '../theme/colors.dart';

/// The four tabs of an approved driver, the offline banner, and the
/// place that starts the shift machinery once.
class AppShell extends ConsumerStatefulWidget {
  const AppShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> {
  bool _started = false;
  StreamSubscription<OutboxRejection>? _rejections;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final l = AppLocalizations.of(context);
    final work = ref.read(workProvider.notifier)..setNotificationTexts(l.trackingNotification, l.trackingNotificationBody);
    final profile = ref.read(profileProvider).value?.profile;
    if (!_started && profile != null) {
      _started = true;
      _rejections = work.rejections.listen((r) {
        if (mounted) showMessage(context, AppLocalizations.of(context).queuedRejected(r.message));
      });
      WidgetsBinding.instance.addPostFrameCallback((_) => work.start(profile));
    }
  }

  @override
  void dispose() {
    _rejections?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final hasNetwork = ref.watch(hasNetworkProvider);

    // A job someone else ended (cancelled) while the driver was on another
    // tab: open the job screen so they see the outcome once.
    ref.listen<WorkState>(workProvider, (previous, next) {
      if (previous?.finishedJob == null && next.finishedJob != null && GoRouter.of(context).routerDelegate.currentConfiguration.uri.path != '/job') {
        context.push('/job');
      }
    });
    final destinations = [
      (Icons.map_outlined, Icons.map_rounded, l.navHome),
      (Icons.receipt_long_outlined, Icons.receipt_long_rounded, l.navJobs),
      (Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, l.navEarnings),
      (Icons.person_outline_rounded, Icons.person_rounded, l.navProfile),
    ];

    return Scaffold(
      body: Column(
        children: [
          AnimatedSize(
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            child: hasNetwork
                ? const SizedBox(width: double.infinity)
                : SafeArea(bottom: false, child: NoticeBanner(icon: Icons.wifi_off_rounded, text: l.offlineBanner)),
          ),
          Expanded(
            child: MediaQuery.removePadding(context: context, removeTop: !hasNetwork, child: widget.shell),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: widget.shell.currentIndex,
        onDestinationSelected: (i) => widget.shell.goBranch(i, initialLocation: i == widget.shell.currentIndex),
        labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
        destinations: [
          for (final (i, d) in destinations.indexed)
            NavigationDestination(
              icon: Icon(d.$1),
              selectedIcon: TweenAnimationBuilder<double>(
                key: ValueKey('nav-$i-${widget.shell.currentIndex}'),
                tween: Tween(begin: 0.8, end: 1),
                duration: const Duration(milliseconds: 260),
                curve: Curves.easeOutBack,
                builder: (context, s, child) => Transform.scale(scale: s, child: child),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                  decoration: BoxDecoration(color: SokoniColors.sokoniYellow, borderRadius: BorderRadius.circular(14)),
                  child: Icon(d.$2, color: SokoniColors.onYellow),
                ),
              ),
              label: d.$3,
            ),
        ],
      ),
    );
  }
}
