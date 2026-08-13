import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../l10n/gen/app_localizations.dart';
import '../motion/motion.dart';

/// Branch order in the app's [StatefulShellRoute] (see app_router.dart): home, search,
/// sell, orders, profile. [AnimatedNavBar] only shows 4 tab icons (Sell is
/// the centre FAB instead), so its item index and the shell's branch index
/// diverge from index 2 onward — this maps one to the other explicitly
/// rather than assuming they line up.
const _navItemBranchIndexes = [0, 1, 3, 4];
const _sellBranchIndex = 2;

/// The five-tab bottom nav shell. Wraps go_router's [StatefulShellRoute]
/// branches in a fade-through transition on tab switch (motion primitive
/// 1) while [IndexedStack] keeps every branch's navigation state alive —
/// switching tabs never resets a branch's scroll position or back stack.
class AppShell extends StatelessWidget {
  const AppShell({required this.navigationShell, super.key});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final currentItemIndex = _navItemBranchIndexes.indexOf(navigationShell.currentIndex);

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: AnimatedNavBar(
        currentIndex: currentItemIndex,
        onTap: (itemIndex) {
          final branchIndex = _navItemBranchIndexes[itemIndex];
          navigationShell.goBranch(branchIndex, initialLocation: branchIndex == navigationShell.currentIndex);
        },
        centerAction: _SellFab(
          onTap: () => navigationShell.goBranch(_sellBranchIndex),
        ),
        items: [
          SokoniNavItem(icon: Icons.home_outlined, selectedIcon: Icons.home_rounded, label: l10n.navHome),
          SokoniNavItem(icon: Icons.search_outlined, selectedIcon: Icons.search_rounded, label: l10n.navSearch),
          SokoniNavItem(icon: Icons.receipt_long_outlined, selectedIcon: Icons.receipt_long_rounded, label: l10n.navOrders),
          SokoniNavItem(icon: Icons.person_outline_rounded, selectedIcon: Icons.person_rounded, label: l10n.navProfile),
        ],
      ),
    );
  }
}

class _SellFab extends StatelessWidget {
  const _SellFab({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 48,
        height: 48,
        decoration: const BoxDecoration(color: Color(0xFFFAC902), shape: BoxShape.circle),
        child: const Icon(Icons.add_rounded, color: Color(0xFF0A0A0A)),
      ),
    );
  }
}

/// Fades the active branch's [IndexedStack] entry in on every tab switch
/// (see [AppShell] docs) while leaving all branches mounted underneath.
class FadeThroughIndexedStack extends StatefulWidget {
  const FadeThroughIndexedStack({required this.index, required this.children, super.key});

  final int index;
  final List<Widget> children;

  @override
  State<FadeThroughIndexedStack> createState() => _FadeThroughIndexedStackState();
}

class _FadeThroughIndexedStackState extends State<FadeThroughIndexedStack>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: kSokoniPageDuration)..value = 1;
  }

  @override
  void didUpdateWidget(covariant FadeThroughIndexedStack oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.index != oldWidget.index) {
      if (MediaQuery.disableAnimationsOf(context)) {
        _controller.value = 1;
      } else {
        _controller.forward(from: 0);
      }
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.3, 1, curve: Curves.easeOut),
      ),
      child: IndexedStack(index: widget.index, children: widget.children),
    );
  }
}
