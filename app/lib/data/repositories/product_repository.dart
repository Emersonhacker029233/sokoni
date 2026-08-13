import 'dart:convert';

import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/dio_client.dart';
import '../api/catalog_api.dart';
import '../models/category.dart';
import '../models/paginated_result.dart';
import '../models/product.dart';
import '../models/product_media.dart';

/// Cache-aware: every list read tries the network first and falls back to
/// the drift cache on [NetworkException] specifically (device offline or
/// unreachable host) — other failures (validation, server errors) surface
/// as-is, since a cached feed can't fix a broken request.
class ProductRepository {
  ProductRepository({required CatalogApi api, required dynamic cache, required Dio dio})
    : _api = api,
      _cache = cache,
      _dio = dio;

  final CatalogApi _api;
  // Media upload is multipart and goes straight through Dio — see
  // SellerRepository's docblock for why retrofit isn't used for this.
  final Dio _dio;
  // Typed as `dynamic` to avoid importing drift's generated AppDatabase
  // type here purely for a parameter annotation — see the concrete calls
  // below, which only use the small cache methods declared on it.
  final dynamic _cache;

  Future<List<SokoniCategory>> categories() async {
    try {
      final json = await _api.categories();
      final list = (json['data'] as List).cast<Map<String, dynamic>>();
      final categories = list.map(SokoniCategory.fromJson).toList();
      await _cache.cacheCategories([
        for (final c in categories) (c.id, jsonEncode(_categoryToJson(c))),
      ]);
      return categories;
    } catch (e) {
      final mapped = mapDioError(e);
      if (mapped is! NetworkException) rethrow;
      final rows = await _cache.readCachedCategoriesJson() as List<String>;
      return rows.map((row) => SokoniCategory.fromJson(jsonDecode(row) as Map<String, dynamic>)).toList();
    }
  }

  Future<PaginatedResult<Product>> products({
    double? lat,
    double? lng,
    double? radiusKm,
    int? categoryId,
    int? sellerId,
    String? query,
    String sort = 'nearby',
    int page = 1,
  }) async {
    try {
      final json = await _api.products({
        'lat': ?lat,
        'lng': ?lng,
        'radius_km': ?radiusKm,
        'category_id': ?categoryId,
        'seller_id': ?sellerId,
        if (query != null && query.isNotEmpty) 'q': query,
        'sort': sort,
        'page': page,
      });
      final result = PaginatedResult<Product>.fromJson(
        json as Map<String, dynamic>,
        Product.fromJson,
      );
      if (page == 1) {
        await _cache.cacheProducts([
          for (final p in result.items) (p.id, p.category?.id, jsonEncode(_productToJson(p))),
        ]);
      }
      return result;
    } catch (e) {
      final mapped = mapDioError(e);
      if (mapped is! NetworkException) rethrow;
      final rows = await _cache.readCachedProductsJson(categoryId: categoryId) as List<String>;
      final items = rows
          .map((row) => Product.fromJson(jsonDecode(row) as Map<String, dynamic>))
          .toList();
      return PaginatedResult(items: items, currentPage: 1, lastPage: 1, total: items.length);
    }
  }

  Future<Product> product(int id) async {
    try {
      final json = await _api.product(id);
      return Product.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> setFavorite(int productId, bool favorited) async {
    try {
      if (favorited) {
        await _api.favorite(productId);
      } else {
        await _api.unfavorite(productId);
      }
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// The signed-in seller's own products, including hidden/pending ones —
  /// see `GET /shop/products`'s docblock server-side.
  Future<PaginatedResult<Product>> myProducts({int page = 1}) async {
    try {
      final json = await _api.myProducts(page);
      return PaginatedResult<Product>.fromJson(json as Map<String, dynamic>, Product.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Product> createProduct({
    required int categoryId,
    required String title,
    String? description,
    required int price,
    required int stock,
    required String condition,
  }) async {
    try {
      final json = await _api.createProduct({
        'category_id': categoryId,
        'title': title,
        'description': description,
        'price': price,
        'stock': stock,
        'condition': condition,
      });
      return Product.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Product> updateProduct({
    required int productId,
    int? categoryId,
    String? title,
    String? description,
    int? price,
    int? stock,
    String? condition,
    bool? isActive,
  }) async {
    try {
      final json = await _api.updateProduct(productId, {
        'category_id': ?categoryId,
        'title': ?title,
        'description': ?description,
        'price': ?price,
        'stock': ?stock,
        'condition': ?condition,
        'is_active': ?isActive,
      });
      return Product.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> deleteProduct(int productId) async {
    try {
      await _api.deleteProduct(productId);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<ProductMediaItem> uploadImageMedia({required int productId, required String imagePath, int? sort}) async {
    try {
      final response = await _dio.post(
        '/products/$productId/media',
        data: FormData.fromMap({
          'type': 'image',
          'file': await MultipartFile.fromFile(imagePath),
          'sort': ?sort,
        }),
      );
      return ProductMediaItem.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<ProductMediaItem> uploadVideoMedia({
    required int productId,
    required String videoPath,
    required String thumbnailPath,
    required int durationSeconds,
    int? sort,
  }) async {
    try {
      final response = await _dio.post(
        '/products/$productId/media',
        data: FormData.fromMap({
          'type': 'video',
          'file': await MultipartFile.fromFile(videoPath),
          'thumbnail': await MultipartFile.fromFile(thumbnailPath),
          'duration': durationSeconds,
          'sort': ?sort,
        }),
      );
      return ProductMediaItem.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> deleteMedia({required int productId, required int mediaId}) async {
    try {
      await _api.deleteMedia(productId, mediaId);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Map<String, dynamic> _categoryToJson(SokoniCategory c) => {
    'id': c.id,
    'parent_id': c.parentId,
    'name_en': c.nameEn,
    'name_sw': c.nameSw,
    'icon': c.icon,
    'sort_order': c.sortOrder,
  };

  Map<String, dynamic> _productToJson(Product p) => {
    'id': p.id,
    'title': p.title,
    'description': p.description,
    'price': p.price,
    'currency': p.currency,
    'stock': p.stock,
    'condition': p.condition,
    'views': p.views,
    'is_active': p.isActive,
    'is_hidden': p.isHidden,
    'distance_km': p.distanceKm,
    'category': p.category == null ? null : _categoryToJson(p.category!),
    'seller': p.seller == null
        ? null
        : {
            'id': p.seller!.id,
            'shop_name': p.seller!.shopName,
            'handle': p.seller!.handle,
            'is_verified': p.seller!.isVerified,
            'rating_avg': p.seller!.ratingAvg,
            'rating_count': p.seller!.ratingCount,
            'lat': p.seller!.lat,
            'lng': p.seller!.lng,
          },
    'media': p.media
        .map(
          (m) => {
            'id': m.id,
            'type': m.type,
            'path': m.path,
            'thumb_path': m.thumbPath,
            'card_path': m.cardPath,
            'duration': m.duration,
            'sort': m.sort,
          },
        )
        .toList(),
    'is_favorited': p.isFavorited,
    'created_at': p.createdAt?.toIso8601String(),
  };
}
