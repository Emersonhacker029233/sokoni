import '../../core/network/dio_client.dart';
import '../api/order_api.dart';
import '../models/order.dart';
import '../models/paginated_result.dart';

class OrderRepository {
  OrderRepository({required OrderApi api}) : _api = api;

  final OrderApi _api;

  Future<PaginatedResult<Order>> myOrders({int page = 1}) async {
    try {
      final json = await _api.myOrders(page);
      return PaginatedResult<Order>.fromJson(json as Map<String, dynamic>, Order.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<PaginatedResult<Order>> shopOrders({int page = 1}) async {
    try {
      final json = await _api.shopOrders(page);
      return PaginatedResult<Order>.fromJson(json as Map<String, dynamic>, Order.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Order> order(int id) async {
    try {
      final json = await _api.order(id);
      return Order.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Order> checkout({
    required List<({int productId, int qty})> items,
    required String deliveryMethod,
    String? address,
    double? deliveryLat,
    double? deliveryLng,
    String? notes,
    required String paymentMethod,
    // Sokoni 1.1 delivery flow only: 'dispatch' plus the Bolt driver's phone.
    String? fulfilment,
    String? deliveryPhone,
  }) async {
    try {
      final json = await _api.checkout({
        'items': [
          for (final item in items) {'product_id': item.productId, 'qty': item.qty},
        ],
        'delivery_method': deliveryMethod,
        'address': ?address,
        'delivery_lat': ?deliveryLat,
        'delivery_lng': ?deliveryLng,
        'notes': ?notes,
        'payment_method': paymentMethod,
        'fulfilment': ?fulfilment,
        'delivery_phone': ?deliveryPhone,
      });
      return Order.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Order> updateStatus({required int orderId, required String status, String? reason}) async {
    try {
      final json = await _api.updateStatus(orderId, {'status': status, 'reason': ?reason});
      return Order.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
