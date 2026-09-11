import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni/shared/widgets/sokoni_avatar.dart';
import 'package:sokoni/shared/widgets/sokoni_network_image.dart';

/// Part A (client feedback, re-diagnosed): shop logos/user avatars used to
/// go through `CircleAvatar.backgroundImage` (an `ImageProvider`), a
/// separate API from the widget-level `SokoniNetworkImage` that already
/// handled the exact same "demo data is SVG, the raster decoder can't
/// read it" gap for product photos — every logo/avatar call site was
/// still the original bug under a different API. Checks the built widget
/// shape per branch rather than letting any image actually load (a real,
/// un-mockable network fetch), same reasoning as
/// `sokoni_network_image_test.dart`.
void main() {
  testWidgets('shows a plain icon when there is no image at all', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: SokoniAvatar(
          imageUrl: null,
          radius: 20,
          fallbackIcon: Icons.storefront_outlined,
          fallbackIconSize: 18,
        ),
      ),
    );

    expect(find.byType(CircleAvatar), findsOneWidget);
    expect(find.byIcon(Icons.storefront_outlined), findsOneWidget);
    expect(find.byType(SokoniNetworkImage), findsNothing);
  });

  testWidgets(
    'a logo URL is clipped to a circle via SokoniNetworkImage — a raster URL here, since '
    'flutter_svg always makes a real network fetch the moment it builds, which throws an '
    'unhandled StateError after this test completes against fake SVG bytes (the SVG-vs-raster '
    'dispatch itself is covered network-free in sokoni_network_image_test.dart)',
    (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: SokoniAvatar(
            imageUrl: 'https://sokoni.co.tz/uploads/products/1/card_abc.jpg',
            radius: 20,
          ),
        ),
      );

      expect(find.byType(CircleAvatar), findsOneWidget);
      expect(find.byType(ClipOval), findsOneWidget);
      final image = tester.widget<SokoniNetworkImage>(
        find.byType(SokoniNetworkImage),
      );
      expect(
        image.imageUrl,
        'https://sokoni.co.tz/uploads/products/1/card_abc.jpg',
      );
      expect(image.width, 40);
      expect(image.height, 40);
    },
  );
}
