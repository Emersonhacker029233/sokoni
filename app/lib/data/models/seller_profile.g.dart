// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'seller_profile.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SellerProfile _$SellerProfileFromJson(Map<String, dynamic> json) =>
    _SellerProfile(
      id: (json['id'] as num).toInt(),
      shopName: json['shop_name'] as String,
      logo: json['logo'] as String?,
      handle: json['handle'] as String,
      bio: json['bio'] as String?,
      category: json['category'] == null
          ? null
          : SokoniCategory.fromJson(json['category'] as Map<String, dynamic>),
      whatsapp: json['whatsapp'] as String?,
      lat: (json['lat'] as num?)?.toDouble(),
      lng: (json['lng'] as num?)?.toDouble(),
      address: json['address'] as String?,
      region: json['region'] as String?,
      district: json['district'] as String?,
      status: json['status'] as String? ?? 'pending',
      rejectionReason: json['rejection_reason'] as String?,
      verifiedAt: json['verified_at'] == null
          ? null
          : DateTime.parse(json['verified_at'] as String),
      ratingAvg: (json['rating_avg'] as num?)?.toDouble() ?? 0,
      ratingCount: (json['rating_count'] as num?)?.toInt() ?? 0,
      customerCount: (json['customer_count'] as num?)?.toInt() ?? 0,
      productsCount: (json['products_count'] as num?)?.toInt() ?? 0,
      followingCount: (json['following_count'] as num?)?.toInt() ?? 0,
      isOwner: json['is_owner'] as bool? ?? false,
      isFollowing: json['is_following'] as bool? ?? false,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
    );

Map<String, dynamic> _$SellerProfileToJson(_SellerProfile instance) =>
    <String, dynamic>{
      'id': instance.id,
      'shop_name': instance.shopName,
      'logo': instance.logo,
      'handle': instance.handle,
      'bio': instance.bio,
      'category': instance.category?.toJson(),
      'whatsapp': instance.whatsapp,
      'lat': instance.lat,
      'lng': instance.lng,
      'address': instance.address,
      'region': instance.region,
      'district': instance.district,
      'status': instance.status,
      'rejection_reason': instance.rejectionReason,
      'verified_at': instance.verifiedAt?.toIso8601String(),
      'rating_avg': instance.ratingAvg,
      'rating_count': instance.ratingCount,
      'customer_count': instance.customerCount,
      'products_count': instance.productsCount,
      'following_count': instance.followingCount,
      'is_owner': instance.isOwner,
      'is_following': instance.isFollowing,
      'created_at': instance.createdAt?.toIso8601String(),
    };
