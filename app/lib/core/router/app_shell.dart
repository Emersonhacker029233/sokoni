import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/social/presentation/widgets/create_sheet.dart';
import '../l10n/gen/app_localizations.dart';
import '../motion/motion.dart';
import '../theme/colors.dart';

/// Branch order in the app's [StatefulShellRoute] (see app_router.dart): home, search,
/// sell, orders, profile. [AnimatedNavBar] only shows 4 tab icons (Sell is
/// the centre FAB instead), so its item index and the shell's branch index
/// diverge from index 2 onward — this maps one to the other explicitly
/// rather than assuming they line up.
const _navItemBranchIndexes = [0, 1, 3, 4];

/// The five-tab bottom nav shell. Wraps go_router's [StatefulShellRoute]
/// branches in a fade-through transition on tab switch (motion primitive
/// 1) while [IndexedStack] keeps every branch's navigation state alive —
/// switching tabs never resets a branch's scroll position or back stack.
class AppShell extends ConsumerWidget {
  const AppShell({required this.navigationShell, super.key});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
        // The "+" opens the create sheet (CLAUDE.md Part 3: Listing / Update
        // / Offer / Showcase for a seller, "Start selling" for a buyer)
        // rather than navigating straight to the Sell branch — that branch
        // is still reachable (e.g. the sheet's own "Listing" option pushes
        // into it), it's just no longer the FAB's first action.
        centerAction: _SellFab(
          onTap: () => showCreateSheet(context, ref),
        ),
        items: [
          SokoniNavItem(icon: Icons.home_outlined, selectedIcon: Icons.home_rounded, label: l10n.navHome),
          // `Icons.search_outlined` renders visually near-identical to its
          // filled counterpart in the bundled Material Icons font (a known
          // limitation for the search glyph specifically — it has no real
          // thin-stroke artwork, unlike home/receipt/person below) — the
          // reported bug: it looked filled/bold even while inactive.
          // `Icons.search` (the classic, pre-Material-3 icon) is a
          // guaranteed normal-weight line glyph, so it's used for the
          // inactive state instead.
          SokoniNavItem(icon: Icons.search, selectedIcon: Icons.search_rounded, label: l10n.navSearch),
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
        decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle),
        child: const Icon(Icons.add_rounded, color: SokoniColors.onYellow),
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
