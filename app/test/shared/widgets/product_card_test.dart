import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/data/models/category.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/data/models/seller_summary.dart';
import 'package:sokoni/shared/widgets/product_card.dart';

Product _buildProduct({double? distanceKm}) {
  return Product(
    id: 1,
    title: 'Samsung Galaxy A54',
    price: 850000,
    currency: 'TZS',
    distanceKm: distanceKm,
    category: const SokoniCategory(id: 1, nameEn: 'Phones', nameSw: 'Simu'),
    seller: const SellerSummary(
      id: 1,
      shopName: 'Amina Electronics',
      handle: 'aminaelectronics',
      isVerified: true,
      ratingAvg: 4.5,
      ratingCount: 12,
    ),
  );
}

void main() {
  // ProductCard is designed to sit in a grid cell with a fixed aspect ratio
  // (see the SliverGrid childAspectRatio: 0.62 in home_screen.dart /
  // search_screen.dart) — its square cover image alone would overflow an
  // arbitrarily-tall parent, so tests give it the same shape production does.
  Widget wrapInGridCell(Widget child) {
    return MaterialApp(
      home: Scaffold(
        body: Align(
          alignment: Alignment.topLeft,
          child: SizedBox(width: 180, height: 180 / 0.62, child: child),
        ),
      ),
    );
  }

  testWidgets('renders title, TZS-formatted price and rating', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      wrapInGridCell(
        ProductCard(
          product: _buildProduct(distanceKm: 2.3),
          onTap: () => tapped = true,
        ),
      ),
    );

    expect(find.text('Samsung Galaxy A54'), findsOneWidget);
    expect(find.text('TSh 850,000'), findsOneWidget);
    // Rating and distance are collapsed into one muted caption line
    // (CLAUDE.md Part 5), not two separately-styled widgets.
    expect(find.text('4.5 · 2.3 km away'), findsOneWidget);
    expect(find.byIcon(Icons.verified_rounded), findsOneWidget);

    await tester.tap(find.byType(ProductCard));
    expect(tapped, isTrue);
  });

  testWidgets('omits distance when the product has none', (tester) async {
    await tester.pumpWidget(wrapInGridCell(ProductCard(product: _buildProduct(), onTap: () {})));

    expect(find.textContaining('km away'), findsNothing);
  });
}
