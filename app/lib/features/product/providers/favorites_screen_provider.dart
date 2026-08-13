import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../data/models/product.dart';
import '../../discovery/providers/discovery_providers.dart';

final favoritesListProvider = FutureProvider<List<Product>>((ref) async {
  final api = ref.watch(catalogApiProvider);
  final json = await api.favorites() as Map<String, dynamic>;
  final data = (json['data'] as List).cast<Map<String, dynamic>>();
  return data.map(Product.fromJson).toList();
});
