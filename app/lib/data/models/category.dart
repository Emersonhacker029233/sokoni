import 'package:freezed_annotation/freezed_annotation.dart';

part 'category.freezed.dart';
part 'category.g.dart';

/// Mirrors `App\Http\Resources\CategoryResource`.
@freezed
abstract class SokoniCategory with _$SokoniCategory {
  const factory SokoniCategory({
    required int id,
    @JsonKey(name: 'parent_id') int? parentId,
    @JsonKey(name: 'name_en') required String nameEn,
    @JsonKey(name: 'name_sw') required String nameSw,
    String? icon,
    @JsonKey(name: 'sort_order') @Default(0) int sortOrder,
  }) = _SokoniCategory;

  factory SokoniCategory.fromJson(Map<String, dynamic> json) => _$SokoniCategoryFromJson(json);
}

extension SokoniCategoryLocalization on SokoniCategory {
  /// Localized name for the given locale code ('en' or 'sw').
  String name(String localeCode) => localeCode == 'sw' ? nameSw : nameEn;

  /// The one category that gets Make/Model attributes (C3, tester
  /// feedback) — mirrors `Category::isCars()` on the backend exactly:
  /// matched by `name_en`, the same stable identifier every other
  /// category-specific special-case already keys on.
  bool get isCars => nameEn == 'Cars';
}
