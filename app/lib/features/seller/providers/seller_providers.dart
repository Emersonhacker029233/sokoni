import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../data/api/seller_api.dart';
import '../../../data/models/paginated_result.dart';
import '../../../data/models/review.dart';
import '../../../data/models/seller_profile.dart';
import '../../../data/repositories/seller_repository.dart';

final sellerApiProvider = Provider<SellerApi>((ref) => SellerApi(ref.watch(dioProvider)));

final sellerRepositoryProvider = Provider<SellerRepository>((ref) {
  return SellerRepository(api: ref.watch(sellerApiProvider));
});

final sellerProfileProvider = FutureProvider.family<SellerProfile, String>((ref, handle) {
  return ref.watch(sellerRepositoryProvider).byHandle(handle);
});

final sellerReviewsProvider = FutureProvider.family<(PaginatedResult<Review>, Map<String, int>), String>(
  (ref, handle) => ref.watch(sellerRepositoryProvider).reviews(handle),
);
