import 'package:flutter/material.dart';

import '../../../../core/theme/colors.dart';

/// Pins the category chip row under the search bar via
/// [SliverPersistentHeader] (CLAUDE.md feature 9).
class StickyCategoryHeaderDelegate extends SliverPersistentHeaderDelegate {
  StickyCategoryHeaderDelegate({required this.child, this.height = 60});

  final Widget child;
  final double height;

  @override
  double get minExtent => height;

  @override
  double get maxExtent => height;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      color: isDark ? SokoniColors.darkSurface : SokoniColors.surface,
      alignment: Alignment.center,
      child: child,
    );
  }

  @override
  bool shouldRebuild(covariant StickyCategoryHeaderDelegate oldDelegate) {
    return oldDelegate.child != child || oldDelegate.height != height;
  }
}
