// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'conversation.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$Conversation {

 int get id; SokoniUser? get buyer; SellerSummary? get seller; Product? get product;@JsonKey(name: 'order_id') int? get orderId;@JsonKey(name: 'last_message') String? get lastMessage;@JsonKey(name: 'last_message_at') DateTime? get lastMessageAt;@JsonKey(name: 'unread_count') int get unreadCount;@JsonKey(name: 'other_party_typing') bool get otherPartyTyping;
/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ConversationCopyWith<Conversation> get copyWith => _$ConversationCopyWithImpl<Conversation>(this as Conversation, _$identity);

  /// Serializes this Conversation to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Conversation&&(identical(other.id, id) || other.id == id)&&(identical(other.buyer, buyer) || other.buyer == buyer)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.orderId, orderId) || other.orderId == orderId)&&(identical(other.lastMessage, lastMessage) || other.lastMessage == lastMessage)&&(identical(other.lastMessageAt, lastMessageAt) || other.lastMessageAt == lastMessageAt)&&(identical(other.unreadCount, unreadCount) || other.unreadCount == unreadCount)&&(identical(other.otherPartyTyping, otherPartyTyping) || other.otherPartyTyping == otherPartyTyping));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,buyer,seller,product,orderId,lastMessage,lastMessageAt,unreadCount,otherPartyTyping);

@override
String toString() {
  return 'Conversation(id: $id, buyer: $buyer, seller: $seller, product: $product, orderId: $orderId, lastMessage: $lastMessage, lastMessageAt: $lastMessageAt, unreadCount: $unreadCount, otherPartyTyping: $otherPartyTyping)';
}


}

/// @nodoc
abstract mixin class $ConversationCopyWith<$Res>  {
  factory $ConversationCopyWith(Conversation value, $Res Function(Conversation) _then) = _$ConversationCopyWithImpl;
@useResult
$Res call({
 int id, SokoniUser? buyer, SellerSummary? seller, Product? product,@JsonKey(name: 'order_id') int? orderId,@JsonKey(name: 'last_message') String? lastMessage,@JsonKey(name: 'last_message_at') DateTime? lastMessageAt,@JsonKey(name: 'unread_count') int unreadCount,@JsonKey(name: 'other_party_typing') bool otherPartyTyping
});


$SokoniUserCopyWith<$Res>? get buyer;$SellerSummaryCopyWith<$Res>? get seller;$ProductCopyWith<$Res>? get product;

}
/// @nodoc
class _$ConversationCopyWithImpl<$Res>
    implements $ConversationCopyWith<$Res> {
  _$ConversationCopyWithImpl(this._self, this._then);

  final Conversation _self;
  final $Res Function(Conversation) _then;

/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? buyer = freezed,Object? seller = freezed,Object? product = freezed,Object? orderId = freezed,Object? lastMessage = freezed,Object? lastMessageAt = freezed,Object? unreadCount = null,Object? otherPartyTyping = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,buyer: freezed == buyer ? _self.buyer : buyer // ignore: cast_nullable_to_non_nullable
as SokoniUser?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as Product?,orderId: freezed == orderId ? _self.orderId : orderId // ignore: cast_nullable_to_non_nullable
as int?,lastMessage: freezed == lastMessage ? _self.lastMessage : lastMessage // ignore: cast_nullable_to_non_nullable
as String?,lastMessageAt: freezed == lastMessageAt ? _self.lastMessageAt : lastMessageAt // ignore: cast_nullable_to_non_nullable
as DateTime?,unreadCount: null == unreadCount ? _self.unreadCount : unreadCount // ignore: cast_nullable_to_non_nullable
as int,otherPartyTyping: null == otherPartyTyping ? _self.otherPartyTyping : otherPartyTyping // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}
/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniUserCopyWith<$Res>? get buyer {
    if (_self.buyer == null) {
    return null;
  }

  return $SokoniUserCopyWith<$Res>(_self.buyer!, (value) {
    return _then(_self.copyWith(buyer: value));
  });
}/// Create a copy of Conversation
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
}/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$ProductCopyWith<$Res>? get product {
    if (_self.product == null) {
    return null;
  }

  return $ProductCopyWith<$Res>(_self.product!, (value) {
    return _then(_self.copyWith(product: value));
  });
}
}


/// Adds pattern-matching-related methods to [Conversation].
extension ConversationPatterns on Conversation {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Conversation value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Conversation() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Conversation value)  $default,){
final _that = this;
switch (_that) {
case _Conversation():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Conversation value)?  $default,){
final _that = this;
switch (_that) {
case _Conversation() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  SokoniUser? buyer,  SellerSummary? seller,  Product? product, @JsonKey(name: 'order_id')  int? orderId, @JsonKey(name: 'last_message')  String? lastMessage, @JsonKey(name: 'last_message_at')  DateTime? lastMessageAt, @JsonKey(name: 'unread_count')  int unreadCount, @JsonKey(name: 'other_party_typing')  bool otherPartyTyping)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Conversation() when $default != null:
return $default(_that.id,_that.buyer,_that.seller,_that.product,_that.orderId,_that.lastMessage,_that.lastMessageAt,_that.unreadCount,_that.otherPartyTyping);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  SokoniUser? buyer,  SellerSummary? seller,  Product? product, @JsonKey(name: 'order_id')  int? orderId, @JsonKey(name: 'last_message')  String? lastMessage, @JsonKey(name: 'last_message_at')  DateTime? lastMessageAt, @JsonKey(name: 'unread_count')  int unreadCount, @JsonKey(name: 'other_party_typing')  bool otherPartyTyping)  $default,) {final _that = this;
switch (_that) {
case _Conversation():
return $default(_that.id,_that.buyer,_that.seller,_that.product,_that.orderId,_that.lastMessage,_that.lastMessageAt,_that.unreadCount,_that.otherPartyTyping);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  SokoniUser? buyer,  SellerSummary? seller,  Product? product, @JsonKey(name: 'order_id')  int? orderId, @JsonKey(name: 'last_message')  String? lastMessage, @JsonKey(name: 'last_message_at')  DateTime? lastMessageAt, @JsonKey(name: 'unread_count')  int unreadCount, @JsonKey(name: 'other_party_typing')  bool otherPartyTyping)?  $default,) {final _that = this;
switch (_that) {
case _Conversation() when $default != null:
return $default(_that.id,_that.buyer,_that.seller,_that.product,_that.orderId,_that.lastMessage,_that.lastMessageAt,_that.unreadCount,_that.otherPartyTyping);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Conversation implements Conversation {
  const _Conversation({required this.id, this.buyer, this.seller, this.product, @JsonKey(name: 'order_id') this.orderId, @JsonKey(name: 'last_message') this.lastMessage, @JsonKey(name: 'last_message_at') this.lastMessageAt, @JsonKey(name: 'unread_count') this.unreadCount = 0, @JsonKey(name: 'other_party_typing') this.otherPartyTyping = false});
  factory _Conversation.fromJson(Map<String, dynamic> json) => _$ConversationFromJson(json);

@override final  int id;
@override final  SokoniUser? buyer;
@override final  SellerSummary? seller;
@override final  Product? product;
@override@JsonKey(name: 'order_id') final  int? orderId;
@override@JsonKey(name: 'last_message') final  String? lastMessage;
@override@JsonKey(name: 'last_message_at') final  DateTime? lastMessageAt;
@override@JsonKey(name: 'unread_count') final  int unreadCount;
@override@JsonKey(name: 'other_party_typing') final  bool otherPartyTyping;

/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ConversationCopyWith<_Conversation> get copyWith => __$ConversationCopyWithImpl<_Conversation>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ConversationToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Conversation&&(identical(other.id, id) || other.id == id)&&(identical(other.buyer, buyer) || other.buyer == buyer)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.orderId, orderId) || other.orderId == orderId)&&(identical(other.lastMessage, lastMessage) || other.lastMessage == lastMessage)&&(identical(other.lastMessageAt, lastMessageAt) || other.lastMessageAt == lastMessageAt)&&(identical(other.unreadCount, unreadCount) || other.unreadCount == unreadCount)&&(identical(other.otherPartyTyping, otherPartyTyping) || other.otherPartyTyping == otherPartyTyping));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,buyer,seller,product,orderId,lastMessage,lastMessageAt,unreadCount,otherPartyTyping);

@override
String toString() {
  return 'Conversation(id: $id, buyer: $buyer, seller: $seller, product: $product, orderId: $orderId, lastMessage: $lastMessage, lastMessageAt: $lastMessageAt, unreadCount: $unreadCount, otherPartyTyping: $otherPartyTyping)';
}


}

/// @nodoc
abstract mixin class _$ConversationCopyWith<$Res> implements $ConversationCopyWith<$Res> {
  factory _$ConversationCopyWith(_Conversation value, $Res Function(_Conversation) _then) = __$ConversationCopyWithImpl;
@override @useResult
$Res call({
 int id, SokoniUser? buyer, SellerSummary? seller, Product? product,@JsonKey(name: 'order_id') int? orderId,@JsonKey(name: 'last_message') String? lastMessage,@JsonKey(name: 'last_message_at') DateTime? lastMessageAt,@JsonKey(name: 'unread_count') int unreadCount,@JsonKey(name: 'other_party_typing') bool otherPartyTyping
});


@override $SokoniUserCopyWith<$Res>? get buyer;@override $SellerSummaryCopyWith<$Res>? get seller;@override $ProductCopyWith<$Res>? get product;

}
/// @nodoc
class __$ConversationCopyWithImpl<$Res>
    implements _$ConversationCopyWith<$Res> {
  __$ConversationCopyWithImpl(this._self, this._then);

  final _Conversation _self;
  final $Res Function(_Conversation) _then;

/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? buyer = freezed,Object? seller = freezed,Object? product = freezed,Object? orderId = freezed,Object? lastMessage = freezed,Object? lastMessageAt = freezed,Object? unreadCount = null,Object? otherPartyTyping = null,}) {
  return _then(_Conversation(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,buyer: freezed == buyer ? _self.buyer : buyer // ignore: cast_nullable_to_non_nullable
as SokoniUser?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as Product?,orderId: freezed == orderId ? _self.orderId : orderId // ignore: cast_nullable_to_non_nullable
as int?,lastMessage: freezed == lastMessage ? _self.lastMessage : lastMessage // ignore: cast_nullable_to_non_nullable
as String?,lastMessageAt: freezed == lastMessageAt ? _self.lastMessageAt : lastMessageAt // ignore: cast_nullable_to_non_nullable
as DateTime?,unreadCount: null == unreadCount ? _self.unreadCount : unreadCount // ignore: cast_nullable_to_non_nullable
as int,otherPartyTyping: null == otherPartyTyping ? _self.otherPartyTyping : otherPartyTyping // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniUserCopyWith<$Res>? get buyer {
    if (_self.buyer == null) {
    return null;
  }

  return $SokoniUserCopyWith<$Res>(_self.buyer!, (value) {
    return _then(_self.copyWith(buyer: value));
  });
}/// Create a copy of Conversation
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
}/// Create a copy of Conversation
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$ProductCopyWith<$Res>? get product {
    if (_self.product == null) {
    return null;
  }

  return $ProductCopyWith<$Res>(_self.product!, (value) {
    return _then(_self.copyWith(product: value));
  });
}
}

// dart format on
