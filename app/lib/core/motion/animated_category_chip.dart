import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/dimens.dart';

/// Motion primitive 5 — animated category chips.
///
/// A single chip that tweens its background to [SokoniColors.sokoniYellow]
/// and its text/border colours when selected, rather than snapping. Used
/// inside a horizontal row where a sliding indicator (this widget's own
/// animated decoration) reads as "morphing" rather than the whole row
/// blanking and repainting.
class AnimatedCategoryChip extends StatelessWidget {
  const AnimatedCategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  static const Duration duration = Duration(milliseconds: 220);

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final unselectedBg = isDark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt;
    final unselectedBorder = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final unselectedText = isDark ? SokoniColors.darkOnSurface : SokoniColors.sokoniBlack;

    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: duration,
          curve: Curves.easeOutCubic,
          constraints: const BoxConstraints(minHeight: SokoniDimens.minTapTarget),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
            color: selected ? SokoniColors.sokoniYellow : unselectedBg,
            borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
            border: Border.all(
              color: selected ? SokoniColors.sokoniYellow : unselectedBorder,
            ),
          ),
          alignment: Alignment.center,
          child: AnimatedDefaultTextStyle(
            duration: duration,
            curve: Curves.easeOutCubic,
            style: Theme.of(context).textTheme.labelLarge!.copyWith(
              color: selected ? SokoniColors.onYellow : unselectedText,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            ),
            child: Text(label),
          ),
        ),
      ),
    );
  }
}

/// A horizontal row of [AnimatedCategoryChip]s. Switching [selectedIndex]
/// cross-fades [child] rather than the caller having to rebuild the whole
/// feed from scratch — wrap feed content in the [child] slot and it will
/// cross-fade whenever the selection (and therefore the feed's key) changes.
class AnimatedChipRow extends StatelessWidget {
  const AnimatedChipRow({
    required this.labels,
    required this.selectedIndex,
    required this.onSelected,
    super.key,
  });

  final List<String> labels;
  final int selectedIndex;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
        itemCount: labels.length,
        separatorBuilder: (_, _) => const SizedBox(width: SokoniDimens.space8),
        itemBuilder: (context, index) {
          return AnimatedCategoryChip(
            label: labels[index],
            selected: index == selectedIndex,
            onTap: () => onSelected(index),
          );
        },
      ),
    );
  }
}

/// Cross-fades [child] whenever [contentKey] changes — pair with
/// [AnimatedChipRow] so the feed beneath it fades between category
/// selections instead of blanking to a loading state.
class CrossFadeSwitcher extends StatelessWidget {
  const CrossFadeSwitcher({
    required this.contentKey,
    required this.child,
    super.key,
  });

  final Object contentKey;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 220),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (child, animation) => FadeTransition(opacity: animation, child: child),
      child: KeyedSubtree(key: ValueKey(contentKey), child: child),
    );
  }
}
