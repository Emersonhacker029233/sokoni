// JSON shaped exactly like `GET /api/delivery/orders/{order}` for a Bolt
// delivery (see docs/delivery/BOLT.md and api/tests/Feature/Delivery/
// CustomerAppContractTest.php). Decimals come as strings, as Laravel
// sends them.

const _driverAssigned = ['driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered'];
const _active = ['driver_assigned', 'arrived_at_shop', 'on_the_way'];

Map<String, dynamic> boltDriverJson({bool withPhone = true}) => {
  'name': 'Seyf Driver',
  'phone': withPhone ? '+255700667788' : null,
  'plate': 'T123ABC',
  'model': 'Toyota IST',
  'photo_url': null,
  'eta_seconds': 180,
};

Map<String, dynamic> buyerView({
  required String status,
  Map<String, dynamic>? attempt,
  List<Map<String, dynamic>>? timeline,
  String? failureReason,
  Map<String, dynamic>? returnBlock,
}) {
  final paid = !['pending_quote', 'pending_payment'].contains(status);
  final active = _active.contains(status);
  return {
    'id': 12,
    'order_id': 345,
    'order_code': 'SKAB12CD34',
    'provider': 'bolt',
    'status': status,
    'is_active': active,
    'distance_km': '4.23',
    'currency': 'TZS',
    'timeline': timeline ??
        [
          {'status': 'pending_quote', 'at': '2026-11-17T08:00:00Z', 'by': 'system', 'reason': null},
          if (status != 'pending_quote') {'status': 'pending_payment', 'at': '2026-11-17T08:00:01Z', 'by': 'system', 'reason': null},
          if (paid) {'status': 'awaiting_ready', 'at': '2026-11-17T08:05:00Z', 'by': 'system', 'reason': null},
          if (paid && status != 'awaiting_ready') {'status': 'pending_driver', 'at': '2026-11-17T08:20:00Z', 'by': 'system', 'reason': null},
          if (_driverAssigned.contains(status)) {'status': 'driver_assigned', 'at': '2026-11-17T08:22:00Z', 'by': 'provider', 'reason': null},
          if (['on_the_way', 'delivered'].contains(status)) {'status': 'on_the_way', 'at': '2026-11-17T08:31:00Z', 'by': 'provider', 'reason': null},
          if (status == 'delivered') {'status': 'delivered', 'at': '2026-11-17T08:50:00Z', 'by': 'provider', 'reason': null},
          if (status == 'cancelled') {'status': 'cancelled', 'at': '2026-11-17T08:20:00Z', 'by': 'system', 'reason': 'payment_timeout'},
        ],
    'cancelled_by': null,
    'cancel_reason': null,
    'failure_reason': failureReason,
    'created_at': '2026-11-17T08:00:00Z',
    'fee': 6400,
    'estimate': {'min': '5200.00', 'max': '6400.00', 'category': 'bolt_send'},
    'payment_status': paid ? 'paid' : 'unpaid',
    'amount_due': status == 'pending_payment' ? 6400 : 0,
    'pickup': {'shop_name': 'Duka la Juma', 'lat': -6.819, 'lng': 39.276},
    'dropoff': {'lat': -6.79, 'lng': 39.28, 'address': 'Mtaa wa Uhuru 12', 'phone': '+255765000123'},
    'payment': {
      'mode': 'online',
      'status': paid ? 'paid' : 'unpaid',
      'amount_due': status == 'pending_payment' ? 6400 : 0,
      'covers': ['delivery_fee'],
      'goods_paid_to': 'shop',
      'goods_total': 20000,
      'delivery_fee': 6400,
      'total': 26400,
      'deadline': status == 'pending_payment' ? DateTime.now().toUtc().add(const Duration(minutes: 18)).toIso8601String() : null,
      'online_available': true,
      'sandbox': true,
      'channels': ['mpesa', 'airtel', 'mixx', 'halopesa'],
      'attempt': attempt,
      'receipt': paid ? {'amount': 6400, 'channel': 'mpesa', 'reference': 'FAKE-approve-1-ABC', 'paid_at': '2026-11-17T08:05:00Z'} : null,
    },
    'driver': _driverAssigned.contains(status) ? boltDriverJson(withPhone: active) : null,
    'tracking_url': active ? 'https://bolt.eu/track/abc' : null,
    'refund': null,
    'actions': {
      'can_pay': status == 'pending_payment',
      'can_cancel': ['pending_payment', 'awaiting_ready', 'pending_driver', 'driver_assigned', 'arrived_at_shop', 'no_driver_found'].contains(status),
      'can_retry': status == 'no_driver_found' && failureReason != 'area_not_serviced',
      'can_switch_to_pickup': ['pending_payment', 'awaiting_ready', 'pending_driver', 'no_driver_found'].contains(status),
    },
    'return': ?returnBlock,
  };
}

/// The seller's view: no fee, payment, drop-off or customer phone.
Map<String, dynamic> sellerView({required String status, bool feePaid = true, Map<String, dynamic>? returnBlock}) {
  final beforePickup = ['driver_assigned', 'arrived_at_shop'].contains(status);
  return {
    'id': 12,
    'order_id': 345,
    'order_code': 'SKAB12CD34',
    'provider': 'bolt',
    'status': status,
    'is_active': _active.contains(status),
    'distance_km': '4.23',
    'currency': 'TZS',
    'timeline': [
      {'status': 'pending_payment', 'at': '2026-11-17T08:00:00Z', 'by': 'system', 'reason': null},
    ],
    'cancelled_by': null,
    'cancel_reason': null,
    'failure_reason': null,
    'created_at': '2026-11-17T08:00:00Z',
    'fee_paid': feePaid,
    'driver': _driverAssigned.contains(status) ? boltDriverJson(withPhone: beforePickup) : null,
    'tracking_url': beforePickup || status == 'pending_driver' ? 'https://bolt.eu/track/abc' : null,
    'return': ?returnBlock,
  };
}

/// An order's `return` block (returns launch only).
Map<String, dynamic> returnBlockJson({String policy = '3d', bool canRequest = true, Map<String, dynamic>? request}) => {
  'policy': {'code': policy, 'hours': 72, 'label': 'Returns within 3 days', 'notes': 'Unused, in the original box.'},
  'eligible_until': '2026-11-20T08:50:00Z',
  'can_request': canRequest,
  'request': request,
};

Map<String, dynamic> returnRequestJson({
  String status = 'requested',
  String reason = 'wrong_item',
  String payer = 'seller',
  Map<String, bool> actions = const {},
  Map<String, dynamic>? ride,
}) => {
  'id': 7,
  'order_id': 345,
  'order_code': 'SKAB12CD34',
  'status': status,
  'reason': reason,
  'reason_label': 'Wrong item',
  'comment': 'It is the blue one, I ordered black.',
  'photos': [
    {'id': 1, 'url': 'https://api.sokoni.co.tz/storage/returns/7/a.jpg?signature=x'},
  ],
  'content_hidden': false,
  'handled_by_support': false,
  'payer': payer,
  'fee': status == 'requested' ? null : 6400,
  'fee_status': status == 'requested' ? null : (payer == 'seller' ? 'owed_by_seller' : 'unpaid'),
  'currency': 'TZS',
  'seller_deadline': status == 'requested' ? '2026-11-19T09:00:00Z' : null,
  'escalated': false,
  'decision': null,
  'dispute': null,
  'resolution_note': null,
  'ride': ride,
  'payment': payer == 'buyer' ? {'amount_due': 6400, 'online_available': true, 'sandbox': true, 'channels': ['mpesa'], 'attempt': null, 'receipt': null} : null,
  'received_at': null,
  'refunded_at': null,
  'closed_at': null,
  'closed_reason': null,
  'created_at': '2026-11-17T09:00:00Z',
  'actions': actions,
};

Map<String, dynamic> deliveryConfigJson() => {
  'enabled': true,
  'provider': 'bolt',
  'min_app_version': '1.1.0',
  'currency': 'TZS',
  'max_distance_km': 10,
  'unpaid_timeout_minutes': 20,
  'payment': {
    'mode': 'online',
    'online_available': true,
    'sandbox': true,
    'channels': ['mpesa', 'airtel', 'mixx', 'halopesa'],
    'covers': ['delivery_fee'],
    'goods_paid_to': 'shop',
  },
  'tracking': {'poll_seconds': 10},
  'returns_enabled': true,
};

Map<String, dynamic> quoteJson({Map<String, dynamic>? returnPolicy}) => {
  'provider': 'bolt',
  'distance_km': 4.23,
  'currency': 'TZS',
  'min': 5200,
  'max': 6400,
  'fee': 6400,
  'category': 'bolt_send',
  'eta_seconds': 240,
  'estimate': true,
  'return_policy': returnPolicy,
};
