import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/data/api/catalog_api.dart';
import 'package:sokoni/data/models/category.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/data/repositories/product_repository.dart';
import 'package:sokoni/features/discovery/providers/discovery_providers.dart';
import 'package:sokoni/features/seller/presentation/product_form/product_form_screen.dart';

class _FakeProductRepository extends ProductRepository {
  _FakeProductRepository(this._product) : super(api: CatalogApi(Dio()), cache: null, dio: Dio());

  final Product _product;

  @override
  Future<Product> product(int id) async => _product;
}

class _CapturingProductRepository extends ProductRepository {
  _CapturingProductRepository() : super(api: CatalogApi(Dio()), cache: null, dio: Dio());

  int? capturedCategoryId;

  @override
  Future<Product> createProduct({
    required int categoryId,
    required String title,
    String? description,
    required int price,
    required int stock,
    required String condition,
    String? make,
    String? model,
  }) async {
    capturedCategoryId = categoryId;
    return Product(id: 1, title: title, price: price, condition: condition);
  }
}

/// D4 (tester feedback): "subcategory picker added last round doesn't
/// work." Diagnosed by actually driving the widget rather than assuming —
/// this codebase had no test for `ProductFormScreen` at all before this.
void main() {
  const electronics = SokoniCategory(id: 1, nameEn: 'Electronics', nameSw: 'Elektroniki', sortOrder: 0);
  const fashion = SokoniCategory(id: 2, nameEn: 'Fashion', nameSw: 'Mitindo', sortOrder: 1);
  const tvs = SokoniCategory(id: 10, parentId: 1, nameEn: 'TVs', nameSw: 'Televisheni', sortOrder: 0);
  const phones = SokoniCategory(id: 11, parentId: 1, nameEn: 'Phones', nameSw: 'Simu', sortOrder: 1);
  const shirts = SokoniCategory(id: 20, parentId: 2, nameEn: 'Shirts', nameSw: 'Mashati', sortOrder: 0);

  Future<void> pumpForm(WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          categoriesProvider.overrideWith((ref) async => [electronics, fashion, tvs, phones, shirts]),
        ],
        child: const MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: ProductFormScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('picking a subcategory shows it selected, not reverted to "use parent"', (tester) async {
    await pumpForm(tester);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Electronics').last);
    await tester.pumpAndSettle();

    final subcategoryField = find.byType(DropdownButtonFormField<int?>);
    expect(subcategoryField, findsOneWidget);

    await tester.tap(subcategoryField);
    await tester.pumpAndSettle();
    await tester.tap(find.text('TVs').last);
    await tester.pumpAndSettle();

    expect(find.text('TVs'), findsOneWidget);
  });

  testWidgets('switching to a different parent category does not crash and resets the subcategory picker', (tester) async {
    await pumpForm(tester);

    // Pick Electronics, then TVs under it.
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Electronics').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<int?>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('TVs').last);
    await tester.pumpAndSettle();
    expect(find.text('TVs'), findsOneWidget);

    // Now switch the parent to Fashion — TVs is no longer a valid option
    // for the subcategory field at all. This must not throw (a stale
    // DropdownButtonFormField holding a value absent from its own new
    // `items` list throws "There should be exactly one item with
    // [DropdownButton]'s value" in the framework) and must not silently
    // keep showing "TVs" as if still selected.
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fashion').last);
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    // The closed dropdown shows only its current selection, which resets
    // to "use parent" (not left showing the now-invalid "TVs").
    expect(find.text('TVs'), findsNothing);

    // Opening it proves the *options* actually updated to Fashion's own
    // children, not left stuck on Electronics'.
    await tester.tap(find.byType(DropdownButtonFormField<int?>));
    await tester.pumpAndSettle();
    expect(find.text('Shirts'), findsOneWidget);
    expect(find.text('TVs'), findsNothing);
    expect(find.text('Phones'), findsNothing);
  });

  testWidgets('editing an existing product already in a subcategory pre-selects both dropdowns', (tester) async {
    const existing = Product(
      id: 99,
      title: 'A TV',
      price: 500000,
      condition: 'used',
      category: tvs,
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          categoriesProvider.overrideWith((ref) async => [electronics, fashion, tvs, phones, shirts]),
          productRepositoryProvider.overrideWithValue(_FakeProductRepository(existing)),
        ],
        child: const MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: ProductFormScreen(productId: 99),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('TVs'), findsOneWidget);
  });

  testWidgets('saving a new product with a subcategory selected sends the subcategory id, not the parent', (tester) async {
    final capturing = _CapturingProductRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          categoriesProvider.overrideWith((ref) async => [electronics, fashion, tvs, phones, shirts]),
          productRepositoryProvider.overrideWithValue(capturing),
        ],
        child: const MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: ProductFormScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Title'), 'A TV');
    await tester.enterText(find.widgetWithText(TextFormField, 'Price'), '500000');
    await tester.enterText(find.widgetWithText(TextFormField, 'Stock'), '1');

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Electronics').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<int?>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('TVs').last);
    await tester.pumpAndSettle();

    final saveButton = find.widgetWithText(FilledButton, 'Save');
    await tester.ensureVisible(saveButton);
    await tester.pumpAndSettle();
    await tester.tap(saveButton);
    await tester.pumpAndSettle();

    expect(capturing.capturedCategoryId, tvs.id);
  });
}
