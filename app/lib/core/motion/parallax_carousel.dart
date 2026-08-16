import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// Motion primitive 11 — image carousel.
///
/// A [PageView] where each page's image shifts slightly against the page
/// scroll (a parallax effect, via [AnimatedBuilder] listening to the
/// [PageController]) and a row of dots below that stretch into a pill for
/// the active page rather than just changing colour.
class ParallaxCarousel extends StatefulWidget {
  const ParallaxCarousel({
    required this.itemCount,
    required this.itemBuilder,
    this.height = 260,
    this.onPageChanged,
    super.key,
  });

  final int itemCount;
  final Widget Function(BuildContext context, int index, double parallax) itemBuilder;
  final double height;
  final ValueChanged<int>? onPageChanged;

  @override
  State<ParallaxCarousel> createState() => _ParallaxCarouselState();
}

class _ParallaxCarouselState extends State<ParallaxCarousel> {
  late final PageController _controller;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: widget.height,
          child: AnimatedBuilder(
            animation: _controller,
            builder: (context, _) {
              double page = _page.toDouble();
              if (_controller.hasClients && _controller.page != null) {
                page = _controller.page!;
              }
              return PageView.builder(
                controller: _controller,
                itemCount: widget.itemCount,
                onPageChanged: (index) {
                  setState(() => _page = index);
                  widget.onPageChanged?.call(index);
                },
                itemBuilder: (context, index) {
                  final delta = (page - index).clamp(-1.0, 1.0);
                  return ClipRect(child: widget.itemBuilder(context, index, delta));
                },
              );
            },
          ),
        ),
        const SizedBox(height: 12),
        StretchDots(count: widget.itemCount, activeIndex: _page),
      ],
    );
  }
}

/// The page-dot-stretch treatment on its own — active dot stretches to a
/// 20×6 pill, inactive dots stay 6×6 circles. [inactiveColor] defaults to
/// the theme's outline colour (right for a carousel on a normal surface);
/// pass an explicit override for content that sits over its own dark
/// background regardless of app theme (e.g. the Showcase video feed).
class StretchDots extends StatelessWidget {
  const StretchDots({required this.count, required this.activeIndex, this.inactiveColor, super.key});

  final int count;
  final int activeIndex;
  final Color? inactiveColor;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final inactive = inactiveColor ?? (isDark ? SokoniColors.darkOutline : SokoniColors.outline);
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(count, (index) {
        final active = index == activeIndex;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          margin: const EdgeInsets.symmetric(horizontal: 3),
          width: active ? 20 : 6,
          height: 6,
          decoration: BoxDecoration(
            color: active ? SokoniColors.sokoniYellow : inactive,
            borderRadius: BorderRadius.circular(3),
          ),
        );
      }),
    );
  }
}

/// A ready-to-use parallaxed image, applying a horizontal translation
/// proportional to [parallax] (-1..1, distance from the current page).
class ParallaxImage extends StatelessWidget {
  const ParallaxImage({required this.image, required this.parallax, super.key});

  final Widget image;
  final double parallax;

  @override
  Widget build(BuildContext context) {
    return OverflowBox(
      minWidth: 0,
      maxWidth: double.infinity,
      child: Transform.translate(
        offset: Offset(parallax * 40, 0),
        child: SizedBox.expand(child: image),
      ),
    );
  }
}
