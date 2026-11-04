/// JSON shaped exactly like `GET /api/delivery/orders/{order}` (see
/// docs/delivery/API.md and api/tests/Feature/Delivery/
/// CustomerAppContractTest.php). Decimals come as strings, as Laravel
/// sends them.
Map<String, dynamic> buyerView({
  required String status,
  bool isActive = false,
  Map<String, dynamic>? attempt,
  List<Map<String, dynamic>>? timeline,
  bool payCash = false,
}) {
  final active = isActive;
  final hasDriver = active || status == 'delivered';
  return {
    'id': 12,
    'order_id': 345,
    'order_code': 'SKAB12CD34',
    'status': status,
    'is_active': active,
    'distance_km': '4.23',
    'currency': 'TZS',
    'timeline': timeline ??
        [
          {'status': 'pending_payment', 'at': '2026-11-04T08:00:00Z', 'by': 'system', 'reason': null},
          if (status != 'pending_payment') {'status': 'pending_driver', 'at': '2026-11-04T08:01:00Z', 'by': 'system', 'reason': null},
          if (active || status == 'delivered') {'status': 'driver_assigned', 'at': '2026-11-04T08:02:00Z', 'by': 'driver', 'reason': null},
          if (['picked_up', 'on_the_way', 'arrived', 'delivered'].contains(status)) {'status': 'picked_up', 'at': '2026-11-04T08:10:00Z', 'by': 'driver', 'reason': null},
          if (['on_the_way', 'arrived', 'delivered'].contains(status)) {'status': 'on_the_way', 'at': '2026-11-04T08:11:00Z', 'by': 'driver', 'reason': null},
          if (status == 'delivered') {'status': 'delivered', 'at': '2026-11-04T08:30:00Z', 'by': 'driver', 'reason': null},
          if (status == 'cancelled') {'status': 'cancelled', 'at': '2026-11-04T08:20:00Z', 'by': 'system', 'reason': 'payment_timeout'},
        ],
    'cancelled_by': null,
    'cancel_reason': null,
    'failure_reason': null,
    'created_at': '2026-11-04T08:00:00Z',
    'fee': 4500,
    'payment_status': status == 'pending_payment' ? 'unpaid' : (payCash ? 'cash' : 'paid'),
    'amount_due': status == 'pending_payment' ? 4500 : 0,
    'pay_cash_to_driver': payCash,
    'delivery_pin': status == 'pending_payment' || status == 'delivered' || status == 'cancelled' ? null : '4821',
    'pin_locked': false,
    'dispatch_wave': status == 'pending_driver' ? 1 : null,
    'pickup': {'shop_name': 'Duka la Juma', 'lat': -6.819, 'lng': 39.276},
    'dropoff': {'lat': -6.79, 'lng': 39.28, 'address': 'Mtaa wa Uhuru 12', 'phone': '+255765000123'},
    'payment': {
      'mode': payCash ? 'cash' : 'online',
      'status': status == 'pending_payment' ? 'unpaid' : 'paid',
      'amount_due': status == 'pending_payment' ? 4500 : 0,
      'covers': ['delivery_fee'],
      'goods_paid_to': 'shop',
      'goods_total': 20000,
      'delivery_fee': 4500,
      'total': 24500,
      'deadline': status == 'pending_payment' ? DateTime.now().toUtc().add(const Duration(minutes: 18)).toIso8601String() : null,
      'online_available': true,
      'sandbox': true,
      'channels': ['mpesa', 'airtel', 'mixx', 'halopesa'],
      'attempt': attempt,
      'receipt': status == 'pending_payment' || payCash
          ? null
          : {'amount': 4500, 'channel': 'mpesa', 'reference': 'FAKE-approve-1-ABC', 'paid_at': '2026-11-04T08:01:00Z'},
    },
    'driver': hasDriver
        ? {
            'name': 'Asha',
            'photo_url': null,
            'rating_avg': '4.80',
            'rating_count': 31,
            'phone': active ? '+255754000000' : null,
            'vehicle': {'type': 'boda_boda', 'plate_number': 'MC123ABC', 'make': 'TVS', 'colour': 'Red'},
          }
        : null,
    'driver_location': active ? {'lat': -6.80, 'lng': 39.279, 'at': '2026-11-04T08:12:00Z'} : null,
    'tracking': active ? {'channel': 'tracking/12'} : null,
    'refund': null,
    'actions': {
      'can_pay': status == 'pending_payment' && !payCash,
      'can_cancel': ['pending_payment', 'pending_driver', 'driver_assigned', 'arrived_at_shop', 'no_driver_found'].contains(status),
      'can_retry': status == 'no_driver_found',
      'can_switch_to_pickup': ['pending_payment', 'pending_driver', 'no_driver_found'].contains(status),
      'can_rate': status == 'delivered',
    },
  };
}

/// The seller's view: no PIN, fee, payment, drop-off or customer phone.
Map<String, dynamic> sellerView({required String status}) {
  final atShop = status == 'driver_assigned' || status == 'arrived_at_shop';
  final active = ['driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived'].contains(status);
  return {
    'id': 12,
    'order_id': 345,
    'order_code': 'SKAB12CD34',
    'status': status,
    'is_active': active,
    'distance_km': '4.23',
    'currency': 'TZS',
    'timeline': [
      {'status': 'pending_payment', 'at': '2026-11-04T08:00:00Z', 'by': 'system', 'reason': null},
    ],
    'cancelled_by': null,
    'cancel_reason': null,
    'failure_reason': null,
    'created_at': '2026-11-04T08:00:00Z',
    'driver': active
        ? {
            'name': 'Asha',
            'photo_url': null,
            'phone': atShop ? '+255754000000' : null,
            'vehicle': {'type': 'boda_boda', 'plate_number': 'MC123ABC', 'make': null, 'colour': null},
          }
        : null,
    'pickup_code': atShop ? 'CD34' : null,
  };
}

Map<String, dynamic> deliveryConfigJson({String mode = 'online'}) => {
  'enabled': true,
  'min_app_version': '1.1.0',
  'currency': 'TZS',
  'max_distance_km': 10,
  'unpaid_timeout_minutes': 20,
  'payment': {
    'mode': mode,
    'online_available': true,
    'sandbox': true,
    'channels': ['mpesa', 'airtel', 'mixx', 'halopesa'],
    'covers': ['delivery_fee'],
    'goods_paid_to': 'shop',
  },
  'tracking': {'poll_seconds': 5, 'realtime': false},
};
