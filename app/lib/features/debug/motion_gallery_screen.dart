import 'package:flutter/material.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/motion/motion.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';

/// Debug route demonstrating all twelve Sokoni motion primitives live.
/// Reachable at `/motion-gallery`.
class MotionGalleryScreen extends StatelessWidget {
  const MotionGalleryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.motionGalleryTitle)),
      body: SokoniRefreshIndicator(
        onRefresh: () => Future<void>.delayed(const Duration(milliseconds: 900)),
        child: ListView(
          padding: const EdgeInsets.symmetric(
            horizontal: SokoniDimens.space16,
            vertical: SokoniDimens.space24,
          ),
          children: [
            _Section(
              number: 1,
              title: l10n.motionGalleryPageTransitions,
              description: l10n.motionGalleryPageTransitionsDesc,
              child: _PageTransitionsDemo(l10n: l10n),
            ),
            _Section(
              number: 2,
              title: l10n.motionGalleryHeroImages,
              description: l10n.motionGalleryHeroImagesDesc,
              child: const _HeroImageDemo(),
            ),
            _Section(
              number: 3,
              title: l10n.motionGalleryStaggeredList,
              description: l10n.motionGalleryStaggeredListDesc,
              child: _StaggeredListDemo(replayLabel: l10n.motionGalleryReplay),
            ),
            _Section(
              number: 4,
              title: l10n.motionGalleryShimmer,
              description: l10n.motionGalleryShimmerDesc,
              child: const SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    ProductCardSkeleton(),
                    SizedBox(width: SokoniDimens.space8),
                    ProductCardSkeleton(),
                  ],
                ),
              ),
            ),
            _Section(
              number: 5,
              title: l10n.motionGalleryChips,
              description: l10n.motionGalleryChipsDesc,
              child: const _ChipDemo(),
            ),
            _Section(
              number: 6,
              title: l10n.motionGalleryBadge,
              description: l10n.motionGalleryBadgeDesc,
              child: const _BadgeDemo(),
            ),
            _Section(
              number: 7,
              title: l10n.motionGalleryBottomSheet,
              description: l10n.motionGalleryBottomSheetDesc,
              child: _OpenSheetButton(label: l10n.motionGalleryOpenSheet, closeLabel: l10n.commonClose),
            ),
            _Section(
              number: 8,
              title: l10n.motionGallerySuccess,
              description: l10n.motionGallerySuccessDesc,
              child: _SuccessButton(label: l10n.motionGalleryPlaySuccess),
            ),
            _Section(
              number: 9,
              title: l10n.motionGalleryNavBar,
              description: l10n.motionGalleryNavBarDesc,
              child: const _NavBarDemo(),
            ),
            _Section(
              number: 10,
              title: l10n.motionGalleryPullToRefresh,
              description: l10n.motionGalleryPullToRefreshDesc,
              child: Text(
                l10n.motionGalleryPullDownHint,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            _Section(
              number: 11,
              title: l10n.motionGalleryCarousel,
              description: l10n.motionGalleryCarouselDesc,
              child: const _CarouselDemo(),
            ),
            _Section(
              number: 12,
              title: l10n.motionGallerySplash,
              description: l10n.motionGallerySplashDesc,
              child: _SplashButton(label: l10n.motionGalleryReplay),
            ),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({
    required this.number,
    required this.title,
    required this.description,
    required this.child,
  });

  final int number;
  final String title;
  final String description;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: SokoniDimens.space32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 24,
                height: 24,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  color: SokoniColors.sokoniYellow,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '$number',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: SokoniColors.onYellow,
                  ),
                ),
              ),
              const SizedBox(width: SokoniDimens.space8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: textTheme.titleMedium),
                    Text(description, style: textTheme.bodySmall),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: SokoniDimens.space12),
          child,
        ],
      ),
    );
  }
}

class _PageTransitionsDemo extends StatelessWidget {
  const _PageTransitionsDemo({required this.l10n});

  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: SokoniDimens.space8,
      children: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).push(
            SharedAxisXPageRoute<void>(child: const _DemoDestination(label: 'Shared-axis X')),
          ),
          child: const Text('Shared-axis X'),
        ),
        OutlinedButton(
          onPressed: () => Navigator.of(context).push(
            SharedAxisZPageRoute<void>(child: const _DemoDestination(label: 'Shared-axis Z')),
          ),
          child: const Text('Shared-axis Z'),
        ),
        OutlinedButton(
          onPressed: () => Navigator.of(context).push(
            FadeThroughPageRoute<void>(child: const _DemoDestination(label: 'Fade-through')),
          ),
          child: const Text('Fade-through'),
        ),
      ],
    );
  }
}

class _DemoDestination extends StatelessWidget {
  const _DemoDestination({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(label)),
      body: Center(
        child: FilledButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Back'),
        ),
      ),
    );
  }
}

class _HeroImageDemo extends StatelessWidget {
  const _HeroImageDemo();

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.of(context).push(
        SharedAxisZPageRoute<void>(
          child: Scaffold(
            appBar: AppBar(title: const Text('Detail')),
            body: Center(
              child: SokoniHeroImage(
                tag: 'gallery-hero-demo',
                borderRadius: 0,
                child: Container(width: 260, height: 260, color: SokoniColors.sokoniYellow),
              ),
            ),
          ),
        ),
      ),
      child: SokoniHeroImage(
        tag: 'gallery-hero-demo',
        child: Container(
          width: 100,
          height: 100,
          color: SokoniColors.sokoniYellow,
          child: const Icon(Icons.touch_app_rounded, color: SokoniColors.onYellow),
        ),
      ),
    );
  }
}

class _StaggeredListDemo extends StatefulWidget {
  const _StaggeredListDemo({required this.replayLabel});

  final String replayLabel;

  @override
  State<_StaggeredListDemo> createState() => _StaggeredListDemoState();
}

class _StaggeredListDemoState extends State<_StaggeredListDemo> {
  int _replayKey = 0;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        StaggeredReveal(
          key: ValueKey(_replayKey),
          children: List.generate(4, (i) {
            return Container(
              margin: const EdgeInsets.only(bottom: SokoniDimens.space8),
              padding: const EdgeInsets.all(SokoniDimens.space12),
              decoration: BoxDecoration(
                color: SokoniColors.surfaceAlt,
                borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
                border: Border.all(color: SokoniColors.outline),
              ),
              child: Text('Item ${i + 1}'),
            );
          }),
        ),
        TextButton(
          onPressed: () => setState(() => _replayKey++),
          child: Text(widget.replayLabel),
        ),
      ],
    );
  }
}

class _ChipDemo extends StatefulWidget {
  const _ChipDemo();

  @override
  State<_ChipDemo> createState() => _ChipDemoState();
}

class _ChipDemoState extends State<_ChipDemo> {
  int _selected = 0;
  static const _labels = ['All', 'Electronics', 'Fashion', 'Food'];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AnimatedChipRow(
          labels: _labels,
          selectedIndex: _selected,
          onSelected: (i) => setState(() => _selected = i),
        ),
        const SizedBox(height: SokoniDimens.space12),
        CrossFadeSwitcher(
          contentKey: _selected,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
            child: Text('Showing: ${_labels[_selected]}'),
          ),
        ),
      ],
    );
  }
}

class _BadgeDemo extends StatefulWidget {
  const _BadgeDemo();

  @override
  State<_BadgeDemo> createState() => _BadgeDemoState();
}

class _BadgeDemoState extends State<_BadgeDemo> {
  int _count = 0;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        BadgeBounce(
          count: _count,
          icon: const Icon(Icons.shopping_cart_rounded, size: 28),
        ),
        const SizedBox(width: SokoniDimens.space16),
        OutlinedButton(
          onPressed: () => setState(() => _count++),
          child: const Text('Add to cart'),
        ),
      ],
    );
  }
}

class _OpenSheetButton extends StatelessWidget {
  const _OpenSheetButton({required this.label, required this.closeLabel});

  final String label;
  final String closeLabel;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: () => showSokoniBottomSheet<void>(
        context: context,
        initialChildSize: 0.35,
        builder: (context) => Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Filters', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space16),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(closeLabel),
            ),
          ],
        ),
      ),
      child: Text(label),
    );
  }
}

class _SuccessButton extends StatelessWidget {
  const _SuccessButton({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: () => showSokoniSuccess(context, message: 'Order placed!'),
      child: Text(label),
    );
  }
}

class _NavBarDemo extends StatefulWidget {
  const _NavBarDemo();

  @override
  State<_NavBarDemo> createState() => _NavBarDemoState();
}

class _NavBarDemoState extends State<_NavBarDemo> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: SokoniColors.outline),
        borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
      ),
      clipBehavior: Clip.antiAlias,
      child: AnimatedNavBar(
        currentIndex: _index,
        onTap: (i) => setState(() => _index = i),
        items: const [
          SokoniNavItem(icon: Icons.home_outlined, selectedIcon: Icons.home_rounded, label: 'Home'),
          SokoniNavItem(icon: Icons.search_outlined, selectedIcon: Icons.search_rounded, label: 'Search'),
          SokoniNavItem(icon: Icons.receipt_long_outlined, selectedIcon: Icons.receipt_long_rounded, label: 'Orders'),
          SokoniNavItem(icon: Icons.person_outline_rounded, selectedIcon: Icons.person_rounded, label: 'Profile'),
        ],
      ),
    );
  }
}

class _CarouselDemo extends StatelessWidget {
  const _CarouselDemo();

  static const _colors = [SokoniColors.sokoniYellow, SokoniColors.sokoniBlack, SokoniColors.success];

  @override
  Widget build(BuildContext context) {
    return ParallaxCarousel(
      height: 140,
      itemCount: _colors.length,
      itemBuilder: (context, index, parallax) {
        return ParallaxImage(
          parallax: parallax,
          image: Container(
            color: _colors[index],
            alignment: Alignment.center,
            child: Text(
              '${index + 1}',
              style: TextStyle(
                fontSize: 32,
                fontWeight: FontWeight.w700,
                color: _colors[index] == SokoniColors.sokoniYellow
                    ? SokoniColors.onYellow
                    : Colors.white,
              ),
            ),
          ),
        );
      },
    );
  }
}

class _SplashButton extends StatelessWidget {
  const _SplashButton({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (context) => SokoniSplashTransition(
            onComplete: () => Navigator.of(context).pop(),
          ),
        ),
      ),
      child: Text(label),
    );
  }
}
