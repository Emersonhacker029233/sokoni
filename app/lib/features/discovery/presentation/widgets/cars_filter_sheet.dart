import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/vehicle_makes.dart';
import '../../../../data/models/category.dart';
import '../../providers/discovery_providers.dart';

/// C3 (tester feedback): "filters on Cars category page." The app browses
/// categories via a flat top-level chip row, not the website's dedicated
/// per-subcategory pages, so this is that page's equivalent for this
/// app's own navigation idiom — reachable once "Vehicles & Parts" (Cars'
/// parent) is the selected chip, via [_ShopsToolbar]'s own conditional
/// icon. "Cars only" narrows [selectedCategoryIdProvider] down to the
/// Cars subcategory's own id (reusing the existing, already-generic
/// [subcategoriesProvider] rather than a Cars-specific lookup), and
/// Make/Model only apply once that's on.
Future<void> showCarsFilterSheet(BuildContext context, WidgetRef ref, int vehiclesPartsCategoryId) {
  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.55,
    builder: (context) => _CarsFilterContent(vehiclesPartsCategoryId: vehiclesPartsCategoryId),
  );
}

class _CarsFilterContent extends ConsumerStatefulWidget {
  const _CarsFilterContent({required this.vehiclesPartsCategoryId});

  final int vehiclesPartsCategoryId;

  @override
  ConsumerState<_CarsFilterContent> createState() => _CarsFilterContentState();
}

class _CarsFilterContentState extends ConsumerState<_CarsFilterContent> {
  late bool _carsOnly = ref.read(selectedCategoryProvider)?.isCars ?? false;
  late String? _make = ref.read(makeFilterProvider);
  late String? _model = ref.read(modelFilterProvider);
  late String? _year = ref.read(yearFilterProvider);

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final subcategoriesAsync = ref.watch(subcategoriesProvider(widget.vehiclesPartsCategoryId));

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.carsFilterTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        subcategoriesAsync.when(
          loading: () => const LinearProgressIndicator(),
          error: (e, _) => const SizedBox.shrink(),
          data: (subcategories) {
            final cars = subcategories.where((c) => c.isCars).firstOrNull;
            if (cars == null) return const SizedBox.shrink();
            return SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l10n.carsFilterCarsOnly),
              value: _carsOnly,
              onChanged: (value) => setState(() {
                _carsOnly = value;
                if (!value) {
                  _make = null;
                  _model = null;
                  _year = null;
                }
              }),
            );
          },
        ),
        if (_carsOnly) ...[
          const SizedBox(height: SokoniDimens.space12),
          DropdownButtonFormField<String>(
            initialValue: _make,
            decoration: InputDecoration(labelText: l10n.productFormMake),
            items: [
              DropdownMenuItem(value: null, child: Text(l10n.carsFilterAnyMake)),
              for (final make in VehicleMakes.makes) DropdownMenuItem(value: make, child: Text(make)),
            ],
            onChanged: (value) => setState(() {
              _make = value;
              _model = null;
              _year = null;
            }),
          ),
          if (_make != null) ...[
            const SizedBox(height: SokoniDimens.space12),
            DropdownButtonFormField<String>(
              initialValue: _model,
              decoration: InputDecoration(labelText: l10n.productFormModel),
              items: [
                DropdownMenuItem(value: null, child: Text(l10n.carsFilterAnyModel)),
                for (final model in VehicleMakes.modelsFor(_make))
                  DropdownMenuItem(value: model, child: Text(model)),
              ],
              onChanged: (value) => setState(() => _model = value),
            ),
          ],
          // C4 (tester feedback): Year — a flat 1990-current range, not
          // narrowed by make/model (see DECISIONS.md), revealed once a
          // model is picked to match the Make -> Model -> Year build-up.
          if (_model != null) ...[
            const SizedBox(height: SokoniDimens.space12),
            DropdownButtonFormField<String>(
              initialValue: _year,
              decoration: InputDecoration(labelText: l10n.productFormYear),
              items: [
                DropdownMenuItem(value: null, child: Text(l10n.carsFilterAnyYear)),
                for (final year in VehicleMakes.years)
                  DropdownMenuItem(value: year.toString(), child: Text(year.toString())),
              ],
              onChanged: (value) => setState(() => _year = value),
            ),
          ],
        ],
        const SizedBox(height: SokoniDimens.space24),
        FilledButton(
          onPressed: () async {
            final subcategories = await ref.read(subcategoriesProvider(widget.vehiclesPartsCategoryId).future);
            final cars = subcategories.where((c) => c.isCars).firstOrNull;
            ref.read(selectedCategoryIdProvider.notifier).state = _carsOnly && cars != null
                ? cars.id
                : widget.vehiclesPartsCategoryId;
            ref.read(makeFilterProvider.notifier).state = _carsOnly ? _make : null;
            ref.read(modelFilterProvider.notifier).state = _carsOnly ? _model : null;
            ref.read(yearFilterProvider.notifier).state = _carsOnly ? _year : null;
            if (context.mounted) Navigator.of(context).pop();
          },
          child: Text(l10n.commonApply),
        ),
      ],
    );
  }
}
