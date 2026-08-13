import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/dimens.dart';

class SokoniNavItem {
  const SokoniNavItem({
    required this.icon,
    required this.selectedIcon,
    required this.label,
  });

  final IconData icon;
  final IconData selectedIcon;
  final String label;
}

/// Motion primitive 9 — nav bar.
///
/// Each tab icon scales up and tweens colour when it becomes active; its
/// label fades in only for the active tab, keeping the bar visually quiet.
/// One [AnimationController] per item, driven only when that item's
/// selected state actually changes (see [_NavIcon.didUpdateWidget]) — nothing
/// re-animates on unrelated rebuilds of the bar.
class AnimatedNavBar extends StatelessWidget {
  const AnimatedNavBar({
    required this.items,
    required this.currentIndex,
    required this.onTap,
    this.centerAction,
    super.key,
  });

  final List<SokoniNavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  /// Optional centre "Sell" FAB-style button, rendered between the two
  /// halves of [items] when the list has an even split.
  final Widget? centerAction;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surface = isDark ? SokoniColors.darkSurface : SokoniColors.surface;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;

    final half = (items.length / 2).ceil();
    final left = items.sublist(0, half);
    final right = items.sublist(half);

    return Container(
      height: SokoniDimens.navBarHeight,
      decoration: BoxDecoration(
        color: surface,
        border: Border(top: BorderSide(color: outline)),
      ),
      child: Row(
        children: [
          ...List.generate(left.length, (i) {
            return Expanded(
              child: _NavIcon(
                item: left[i],
                selected: currentIndex == i,
                onTap: () => onTap(i),
              ),
            );
          }),
          if (centerAction != null)
            SizedBox(width: SokoniDimens.navFabSize + 16, child: Center(child: centerAction)),
          ...List.generate(right.length, (i) {
            final index = half + i;
            return Expanded(
              child: _NavIcon(
                item: right[i],
                selected: currentIndex == index,
                onTap: () => onTap(index),
              ),
            );
          }),
        ],
      ),
    );
  }
}

class _NavIcon extends StatefulWidget {
  const _NavIcon({required this.item, required this.selected, required this.onTap});

  final SokoniNavItem item;
  final bool selected;
  final VoidCallback onTap;

  @override
  State<_NavIcon> createState() => _NavIconState();
}

class _NavIconState extends State<_NavIcon> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 220),
      value: widget.selected ? 1 : 0,
    );
  }

  @override
  void didUpdateWidget(covariant _NavIcon oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.selected != oldWidget.selected) {
      final disableAnimations = MediaQuery.disableAnimationsOf(context);
      if (widget.selected) {
        disableAnimations ? _controller.value = 1 : _controller.forward();
      } else {
        disableAnimations ? _controller.value = 0 : _controller.reverse();
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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final inactiveColor = (isDark ? SokoniColors.darkOnSurface : SokoniColors.sokoniBlack)
        .withValues(alpha: 0.5);
    const activeColor = SokoniColors.sokoniBlack;

    return Semantics(
      button: true,
      selected: widget.selected,
      label: widget.item.label,
      child: InkWell(
        onTap: widget.onTap,
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            final t = Curves.easeOutCubic.transform(_controller.value);
            final color = Color.lerp(inactiveColor, activeColor, t)!;
            final scale = 1 + t * 0.15;
            return Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Transform.scale(
                  scale: scale,
                  child: Icon(
                    widget.selected ? widget.item.selectedIcon : widget.item.icon,
                    color: color,
                    size: 24,
                  ),
                ),
                ClipRect(
                  child: Align(
                    heightFactor: t,
                    child: Opacity(
                      opacity: t,
                      child: Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(
                          widget.item.label,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: activeColor,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
