// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'product.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$Product {

 int get id; String get title; String? get description; int get price; String get currency; int get stock; String get condition; int get views;@JsonKey(name: 'is_active') bool get isActive;@JsonKey(name: 'is_hidden') bool get isHidden;@JsonKey(name: 'is_sponsored') bool get isSponsored;@JsonKey(name: 'sponsor_contact_method') String? get sponsorContactMethod;@JsonKey(name: 'comments_count') int? get commentsCount;@JsonKey(name: 'distance_km') double? get distanceKm; SokoniCategory? get category; SellerSummary? get seller; List<ProductMediaItem> get media;@JsonKey(name: 'is_favorited') bool get isFavorited;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ProductCopyWith<Product> get copyWith => _$ProductCopyWithImpl<Product>(this as Product, _$identity);

  /// Serializes this Product to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Product&&(identical(other.id, id) || other.id == id)&&(identical(other.title, title) || other.title == title)&&(identical(other.description, description) || other.description == description)&&(identical(other.price, price) || other.price == price)&&(identical(other.currency, currency) || other.currency == currency)&&(identical(other.stock, stock) || other.stock == stock)&&(identical(other.condition, condition) || other.condition == condition)&&(identical(other.views, views) || other.views == views)&&(identical(other.isActive, isActive) || other.isActive == isActive)&&(identical(other.isHidden, isHidden) || other.isHidden == isHidden)&&(identical(other.isSponsored, isSponsored) || other.isSponsored == isSponsored)&&(identical(other.sponsorContactMethod, sponsorContactMethod) || other.sponsorContactMethod == sponsorContactMethod)&&(identical(other.commentsCount, commentsCount) || other.commentsCount == commentsCount)&&(identical(other.distanceKm, distanceKm) || other.distanceKm == distanceKm)&&(identical(other.category, category) || other.category == category)&&(identical(other.seller, seller) || other.seller == seller)&&const DeepCollectionEquality().equals(other.media, media)&&(identical(other.isFavorited, isFavorited) || other.isFavorited == isFavorited)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,title,description,price,currency,stock,condition,views,isActive,isHidden,isSponsored,sponsorContactMethod,commentsCount,distanceKm,category,seller,const DeepCollectionEquality().hash(media),isFavorited,createdAt]);

@override
String toString() {
  return 'Product(id: $id, title: $title, description: $description, price: $price, currency: $currency, stock: $stock, condition: $condition, views: $views, isActive: $isActive, isHidden: $isHidden, isSponsored: $isSponsored, sponsorContactMethod: $sponsorContactMethod, commentsCount: $commentsCount, distanceKm: $distanceKm, category: $category, seller: $seller, media: $media, isFavorited: $isFavorited, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $ProductCopyWith<$Res>  {
  factory $ProductCopyWith(Product value, $Res Function(Product) _then) = _$ProductCopyWithImpl;
@useResult
$Res call({
 int id, String title, String? description, int price, String currency, int stock, String condition, int views,@JsonKey(name: 'is_active') bool isActive,@JsonKey(name: 'is_hidden') bool isHidden,@JsonKey(name: 'is_sponsored') bool isSponsored,@JsonKey(name: 'sponsor_contact_method') String? sponsorContactMethod,@JsonKey(name: 'comments_count') int? commentsCount,@JsonKey(name: 'distance_km') double? distanceKm, SokoniCategory? category, SellerSummary? seller, List<ProductMediaItem> media,@JsonKey(name: 'is_favorited') bool isFavorited,@JsonKey(name: 'created_at') DateTime? createdAt
});


$SokoniCategoryCopyWith<$Res>? get category;$SellerSummaryCopyWith<$Res>? get seller;

}
/// @nodoc
class _$ProductCopyWithImpl<$Res>
    implements $ProductCopyWith<$Res> {
  _$ProductCopyWithImpl(this._self, this._then);

  final Product _self;
  final $Res Function(Product) _then;

/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? title = null,Object? description = freezed,Object? price = null,Object? currency = null,Object? stock = null,Object? condition = null,Object? views = null,Object? isActive = null,Object? isHidden = null,Object? isSponsored = null,Object? sponsorContactMethod = freezed,Object? commentsCount = freezed,Object? distanceKm = freezed,Object? category = freezed,Object? seller = freezed,Object? media = null,Object? isFavorited = null,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,title: null == title ? _self.title : title // ignore: cast_nullable_to_non_nullable
as String,description: freezed == description ? _self.description : description // ignore: cast_nullable_to_non_nullable
as String?,price: null == price ? _self.price : price // ignore: cast_nullable_to_non_nullable
as int,currency: null == currency ? _self.currency : currency // ignore: cast_nullable_to_non_nullable
as String,stock: null == stock ? _self.stock : stock // ignore: cast_nullable_to_non_nullable
as int,condition: null == condition ? _self.condition : condition // ignore: cast_nullable_to_non_nullable
as String,views: null == views ? _self.views : views // ignore: cast_nullable_to_non_nullable
as int,isActive: null == isActive ? _self.isActive : isActive // ignore: cast_nullable_to_non_nullable
as bool,isHidden: null == isHidden ? _self.isHidden : isHidden // ignore: cast_nullable_to_non_nullable
as bool,isSponsored: null == isSponsored ? _self.isSponsored : isSponsored // ignore: cast_nullable_to_non_nullable
as bool,sponsorContactMethod: freezed == sponsorContactMethod ? _self.sponsorContactMethod : sponsorContactMethod // ignore: cast_nullable_to_non_nullable
as String?,commentsCount: freezed == commentsCount ? _self.commentsCount : commentsCount // ignore: cast_nullable_to_non_nullable
as int?,distanceKm: freezed == distanceKm ? _self.distanceKm : distanceKm // ignore: cast_nullable_to_non_nullable
as double?,category: freezed == category ? _self.category : category // ignore: cast_nullable_to_non_nullable
as SokoniCategory?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,media: null == media ? _self.media : media // ignore: cast_nullable_to_non_nullable
as List<ProductMediaItem>,isFavorited: null == isFavorited ? _self.isFavorited : isFavorited // ignore: cast_nullable_to_non_nullable
as bool,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniCategoryCopyWith<$Res>? get category {
    if (_self.category == null) {
    return null;
  }

  return $SokoniCategoryCopyWith<$Res>(_self.category!, (value) {
    return _then(_self.copyWith(category: value));
  });
}/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SellerSummaryCopyWith<$Res>? get seller {
    if (_self.seller == null) {
    return null;
  }

  return $SellerSummaryCopyWith<$Res>(_self.seller!, (value) {
    return _then(_self.copyWith(seller: value));
  });
}
}


/// Adds pattern-matching-related methods to [Product].
extension ProductPatterns on Product {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Product value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Product() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Product value)  $default,){
final _that = this;
switch (_that) {
case _Product():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Product value)?  $default,){
final _that = this;
switch (_that) {
case _Product() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String title,  String? description,  int price,  String currency,  int stock,  String condition,  int views, @JsonKey(name: 'is_active')  bool isActive, @JsonKey(name: 'is_hidden')  bool isHidden, @JsonKey(name: 'is_sponsored')  bool isSponsored, @JsonKey(name: 'sponsor_contact_method')  String? sponsorContactMethod, @JsonKey(name: 'comments_count')  int? commentsCount, @JsonKey(name: 'distance_km')  double? distanceKm,  SokoniCategory? category,  SellerSummary? seller,  List<ProductMediaItem> media, @JsonKey(name: 'is_favorited')  bool isFavorited, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Product() when $default != null:
return $default(_that.id,_that.title,_that.description,_that.price,_that.currency,_that.stock,_that.condition,_that.views,_that.isActive,_that.isHidden,_that.isSponsored,_that.sponsorContactMethod,_that.commentsCount,_that.distanceKm,_that.category,_that.seller,_that.media,_that.isFavorited,_that.createdAt);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String title,  String? description,  int price,  String currency,  int stock,  String condition,  int views, @JsonKey(name: 'is_active')  bool isActive, @JsonKey(name: 'is_hidden')  bool isHidden, @JsonKey(name: 'is_sponsored')  bool isSponsored, @JsonKey(name: 'sponsor_contact_method')  String? sponsorContactMethod, @JsonKey(name: 'comments_count')  int? commentsCount, @JsonKey(name: 'distance_km')  double? distanceKm,  SokoniCategory? category,  SellerSummary? seller,  List<ProductMediaItem> media, @JsonKey(name: 'is_favorited')  bool isFavorited, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _Product():
return $default(_that.id,_that.title,_that.description,_that.price,_that.currency,_that.stock,_that.condition,_that.views,_that.isActive,_that.isHidden,_that.isSponsored,_that.sponsorContactMethod,_that.commentsCount,_that.distanceKm,_that.category,_that.seller,_that.media,_that.isFavorited,_that.createdAt);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String title,  String? description,  int price,  String currency,  int stock,  String condition,  int views, @JsonKey(name: 'is_active')  bool isActive, @JsonKey(name: 'is_hidden')  bool isHidden, @JsonKey(name: 'is_sponsored')  bool isSponsored, @JsonKey(name: 'sponsor_contact_method')  String? sponsorContactMethod, @JsonKey(name: 'comments_count')  int? commentsCount, @JsonKey(name: 'distance_km')  double? distanceKm,  SokoniCategory? category,  SellerSummary? seller,  List<ProductMediaItem> media, @JsonKey(name: 'is_favorited')  bool isFavorited, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _Product() when $default != null:
return $default(_that.id,_that.title,_that.description,_that.price,_that.currency,_that.stock,_that.condition,_that.views,_that.isActive,_that.isHidden,_that.isSponsored,_that.sponsorContactMethod,_that.commentsCount,_that.distanceKm,_that.category,_that.seller,_that.media,_that.isFavorited,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Product implements Product {
  const _Product({required this.id, required this.title, this.description, required this.price, this.currency = 'TZS', this.stock = 0, this.condition = 'new', this.views = 0, @JsonKey(name: 'is_active') this.isActive = true, @JsonKey(name: 'is_hidden') this.isHidden = false, @JsonKey(name: 'is_sponsored') this.isSponsored = false, @JsonKey(name: 'sponsor_contact_method') this.sponsorContactMethod, @JsonKey(name: 'comments_count') this.commentsCount, @JsonKey(name: 'distance_km') this.distanceKm, this.category, this.seller, final  List<ProductMediaItem> media = const <ProductMediaItem>[], @JsonKey(name: 'is_favorited') this.isFavorited = false, @JsonKey(name: 'created_at') this.createdAt}): _media = media;
  factory _Product.fromJson(Map<String, dynamic> json) => _$ProductFromJson(json);

@override final  int id;
@override final  String title;
@override final  String? description;
@override final  int price;
@override@JsonKey() final  String currency;
@override@JsonKey() final  int stock;
@override@JsonKey() final  String condition;
@override@JsonKey() final  int views;
@override@JsonKey(name: 'is_active') final  bool isActive;
@override@JsonKey(name: 'is_hidden') final  bool isHidden;
@override@JsonKey(name: 'is_sponsored') final  bool isSponsored;
@override@JsonKey(name: 'sponsor_contact_method') final  String? sponsorContactMethod;
@override@JsonKey(name: 'comments_count') final  int? commentsCount;
@override@JsonKey(name: 'distance_km') final  double? distanceKm;
@override final  SokoniCategory? category;
@override final  SellerSummary? seller;
 final  List<ProductMediaItem> _media;
@override@JsonKey() List<ProductMediaItem> get media {
  if (_media is EqualUnmodifiableListView) return _media;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_media);
}

@override@JsonKey(name: 'is_favorited') final  bool isFavorited;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ProductCopyWith<_Product> get copyWith => __$ProductCopyWithImpl<_Product>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ProductToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Product&&(identical(other.id, id) || other.id == id)&&(identical(other.title, title) || other.title == title)&&(identical(other.description, description) || other.description == description)&&(identical(other.price, price) || other.price == price)&&(identical(other.currency, currency) || other.currency == currency)&&(identical(other.stock, stock) || other.stock == stock)&&(identical(other.condition, condition) || other.condition == condition)&&(identical(other.views, views) || other.views == views)&&(identical(other.isActive, isActive) || other.isActive == isActive)&&(identical(other.isHidden, isHidden) || other.isHidden == isHidden)&&(identical(other.isSponsored, isSponsored) || other.isSponsored == isSponsored)&&(identical(other.sponsorContactMethod, sponsorContactMethod) || other.sponsorContactMethod == sponsorContactMethod)&&(identical(other.commentsCount, commentsCount) || other.commentsCount == commentsCount)&&(identical(other.distanceKm, distanceKm) || other.distanceKm == distanceKm)&&(identical(other.category, category) || other.category == category)&&(identical(other.seller, seller) || other.seller == seller)&&const DeepCollectionEquality().equals(other._media, _media)&&(identical(other.isFavorited, isFavorited) || other.isFavorited == isFavorited)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,title,description,price,currency,stock,condition,views,isActive,isHidden,isSponsored,sponsorContactMethod,commentsCount,distanceKm,category,seller,const DeepCollectionEquality().hash(_media),isFavorited,createdAt]);

@override
String toString() {
  return 'Product(id: $id, title: $title, description: $description, price: $price, currency: $currency, stock: $stock, condition: $condition, views: $views, isActive: $isActive, isHidden: $isHidden, isSponsored: $isSponsored, sponsorContactMethod: $sponsorContactMethod, commentsCount: $commentsCount, distanceKm: $distanceKm, category: $category, seller: $seller, media: $media, isFavorited: $isFavorited, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$ProductCopyWith<$Res> implements $ProductCopyWith<$Res> {
  factory _$ProductCopyWith(_Product value, $Res Function(_Product) _then) = __$ProductCopyWithImpl;
@override @useResult
$Res call({
 int id, String title, String? description, int price, String currency, int stock, String condition, int views,@JsonKey(name: 'is_active') bool isActive,@JsonKey(name: 'is_hidden') bool isHidden,@JsonKey(name: 'is_sponsored') bool isSponsored,@JsonKey(name: 'sponsor_contact_method') String? sponsorContactMethod,@JsonKey(name: 'comments_count') int? commentsCount,@JsonKey(name: 'distance_km') double? distanceKm, SokoniCategory? category, SellerSummary? seller, List<ProductMediaItem> media,@JsonKey(name: 'is_favorited') bool isFavorited,@JsonKey(name: 'created_at') DateTime? createdAt
});


@override $SokoniCategoryCopyWith<$Res>? get category;@override $SellerSummaryCopyWith<$Res>? get seller;

}
/// @nodoc
class __$ProductCopyWithImpl<$Res>
    implements _$ProductCopyWith<$Res> {
  __$ProductCopyWithImpl(this._self, this._then);

  final _Product _self;
  final $Res Function(_Product) _then;

/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? title = null,Object? description = freezed,Object? price = null,Object? currency = null,Object? stock = null,Object? condition = null,Object? views = null,Object? isActive = null,Object? isHidden = null,Object? isSponsored = null,Object? sponsorContactMethod = freezed,Object? commentsCount = freezed,Object? distanceKm = freezed,Object? category = freezed,Object? seller = freezed,Object? media = null,Object? isFavorited = null,Object? createdAt = freezed,}) {
  return _then(_Product(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,title: null == title ? _self.title : title // ignore: cast_nullable_to_non_nullable
as String,description: freezed == description ? _self.description : description // ignore: cast_nullable_to_non_nullable
as String?,price: null == price ? _self.price : price // ignore: cast_nullable_to_non_nullable
as int,currency: null == currency ? _self.currency : currency // ignore: cast_nullable_to_non_nullable
as String,stock: null == stock ? _self.stock : stock // ignore: cast_nullable_to_non_nullable
as int,condition: null == condition ? _self.condition : condition // ignore: cast_nullable_to_non_nullable
as String,views: null == views ? _self.views : views // ignore: cast_nullable_to_non_nullable
as int,isActive: null == isActive ? _self.isActive : isActive // ignore: cast_nullable_to_non_nullable
as bool,isHidden: null == isHidden ? _self.isHidden : isHidden // ignore: cast_nullable_to_non_nullable
as bool,isSponsored: null == isSponsored ? _self.isSponsored : isSponsored // ignore: cast_nullable_to_non_nullable
as bool,sponsorContactMethod: freezed == sponsorContactMethod ? _self.sponsorContactMethod : sponsorContactMethod // ignore: cast_nullable_to_non_nullable
as String?,commentsCount: freezed == commentsCount ? _self.commentsCount : commentsCount // ignore: cast_nullable_to_non_nullable
as int?,distanceKm: freezed == distanceKm ? _self.distanceKm : distanceKm // ignore: cast_nullable_to_non_nullable
as double?,category: freezed == category ? _self.category : category // ignore: cast_nullable_to_non_nullable
as SokoniCategory?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,media: null == media ? _self._media : media // ignore: cast_nullable_to_non_nullable
as List<ProductMediaItem>,isFavorited: null == isFavorited ? _self.isFavorited : isFavorited // ignore: cast_nullable_to_non_nullable
as bool,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniCategoryCopyWith<$Res>? get category {
    if (_self.category == null) {
    return null;
  }

  return $SokoniCategoryCopyWith<$Res>(_self.category!, (value) {
    return _then(_self.copyWith(category: value));
  });
}/// Create a copy of Product
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SellerSummaryCopyWith<$Res>? get seller {
    if (_self.seller == null) {
    return null;
  }

  return $SellerSummaryCopyWith<$Res>(_self.seller!, (value) {
    return _then(_self.copyWith(seller: value));
  });
}
}

// dart format on
