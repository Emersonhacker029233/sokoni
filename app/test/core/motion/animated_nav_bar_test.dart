import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/motion/animated_nav_bar.dart';
import 'package:sokoni/core/theme/dimens.dart';

/// D1 (tester feedback): the bottom nav previously had no SafeArea at
/// all, so a gesture-nav device's home-indicator strip drew right on top
/// of the icons/labels. Covers all three device configurations named in
/// the report: gesture nav (a real bottom inset), three-button nav and an
/// insetless device (both report `viewPadding.bottom == 0`, and must
/// render identically to how the bar always has).
void main() {
  const items = [
    SokoniNavItem(icon: Icons.home_outlined, selectedIcon: Icons.home_rounded, label: 'Home'),
    SokoniNavItem(icon: Icons.search, selectedIcon: Icons.search_rounded, label: 'Search'),
  ];

  Widget buildBar(double bottomInset) {
    return MediaQuery(
      data: MediaQueryData(viewPadding: EdgeInsets.only(bottom: bottomInset), padding: EdgeInsets.only(bottom: bottomInset)),
      child: MaterialApp(
        home: Scaffold(
          bottomNavigationBar: AnimatedNavBar(items: items, currentIndex: 0, onTap: (_) {}),
        ),
      ),
    );
  }

  testWidgets('on a gesture-nav device, the bar grows by exactly the system inset', (tester) async {
    await tester.pumpWidget(buildBar(24));

    final barSize = tester.getSize(find.byType(AnimatedNavBar));
    expect(barSize.height, SokoniDimens.navBarHeight + 24);
  });

  testWidgets('on a three-button-nav device (zero inset), the bar height is unchanged', (tester) async {
    await tester.pumpWidget(buildBar(0));

    final barSize = tester.getSize(find.byType(AnimatedNavBar));
    expect(barSize.height, SokoniDimens.navBarHeight);
  });

  testWidgets('on an insetless device, the bar height is unchanged', (tester) async {
    await tester.pumpWidget(buildBar(0));

    expect(find.byType(SafeArea), findsWidgets);
    final barSize = tester.getSize(find.byType(AnimatedNavBar));
    expect(barSize.height, SokoniDimens.navBarHeight);
  });

  testWidgets('the nav icons themselves never shrink to accommodate the inset — they sit above it', (tester) async {
    await tester.pumpWidget(buildBar(24));

    // The tappable icon area keeps its full height; the inset is extra
    // space added below it, not room taken from it.
    final iconFinder = find.byIcon(Icons.home_rounded);
    final iconBottom = tester.getBottomLeft(iconFinder).dy;
    final barBottom = tester.getBottomLeft(find.byType(AnimatedNavBar)).dy;

    // The icon sits comfortably above the very bottom of the bar's total
    // (inset-inclusive) footprint — the 24px inset is unused blank space
    // beneath it, not squeezed-into space.
    expect(barBottom - iconBottom, greaterThanOrEqualTo(24));
  });
}
