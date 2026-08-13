import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

import '../theme/colors.dart';
import '../theme/dimens.dart';

/// Motion primitive 4 — shimmer skeletons.
///
/// A shared shimmer wrapper plus a handful of primitive shapes
/// (`SkeletonBox`, `SkeletonLine`, `SkeletonCircle`) that feature-specific
/// skeletons (in `shared/skeletons/`) compose into layouts matching their
/// real screens. No spinners are used anywhere except pull-to-refresh.
class SokoniShimmer extends StatelessWidget {
  const SokoniShimmer({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final base = isDark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt;
    final highlight = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    return Shimmer.fromColors(
      baseColor: base,
      highlightColor: highlight,
      period: const Duration(milliseconds: 1400),
      child: child,
    );
  }
}

class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    required this.width,
    required this.height,
    this.borderRadius = SokoniDimens.radiusField,
    super.key,
  });

  final double width;
  final double height;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
    );
  }
}

class SkeletonLine extends StatelessWidget {
  const SkeletonLine({this.width, this.height = 14, super.key});

  final double? width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return SkeletonBox(width: width ?? double.infinity, height: height, borderRadius: 6);
  }
}

class SkeletonCircle extends StatelessWidget {
  const SkeletonCircle({required this.diameter, super.key});

  final double diameter;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: diameter,
      height: diameter,
      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
    );
  }
}

/// A ready-made shimmering product card skeleton, used by the discovery
/// feed and as a motion gallery demo of primitive 4.
class ProductCardSkeleton extends StatelessWidget {
  const ProductCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SokoniShimmer(
      child: Container(
        width: 160,
        padding: const EdgeInsets.all(SokoniDimens.space8),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          border: Border.all(color: Colors.white),
        ),
        child: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SkeletonBox(width: double.infinity, height: 120),
            SizedBox(height: SokoniDimens.space8),
            SkeletonLine(width: 110),
            SizedBox(height: SokoniDimens.space4),
            SkeletonLine(width: 70),
            SizedBox(height: SokoniDimens.space8),
            Row(
              children: [
                SkeletonCircle(diameter: 16),
                SizedBox(width: SokoniDimens.space4),
                SkeletonLine(width: 60, height: 10),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
