// Sokoni 1.1 delivery models (docs/delivery/API.md, "Customer app").
//
// Hand-written rather than freezed: the server sends some numbers as
// strings (Laravel decimal casts) and several blocks only to one viewer
// (the buyer gets `payment`, `delivery_pin`, `dropoff`; the seller gets
// `pickup_code`), so every field is parsed defensively here. Every amount
// in these classes is a number the server stored; the app never derives
// a fee, a total or a payment status of its own.

int _int(Object? v, [int fallback = 0]) => switch (v) {
  final int i => i,
  final num n => n.round(),
  final String s => num.tryParse(s)?.round() ?? fallback,
  _ => fallback,
};

int? _intOrNull(Object? v) => v == null ? null : _int(v);

double? _double(Object? v) => switch (v) {
  final num n => n.toDouble(),
  final String s => double.tryParse(s),
  _ => null,
};

bool _bool(Object? v) => v == true || v == 1 || v == '1' || v == 'true';

String? _string(Object? v) => v == null ? null : '$v';

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v)?.toLocal() : null;

List<String> _strings(Object? v) => v is List ? [for (final e in v) '$e'] : const [];

Map<String, dynamic>? _map(Object? v) => v is Map ? v.cast<String, dynamic>() : null;

/// `GET /delivery/config`.
class DeliveryConfig {
  const DeliveryConfig({
    required this.enabled,
    required this.minAppVersion,
    required this.maxDistanceKm,
    required this.unpaidTimeoutMinutes,
    required this.paymentMode,
    required this.onlinePaymentAvailable,
    required this.sandbox,
    required this.channels,
    required this.pollSeconds,
    required this.realtime,
  });

  factory DeliveryConfig.fromJson(Map<String, dynamic> json) {
    final payment = _map(json['payment']) ?? const {};
    final tracking = _map(json['tracking']) ?? const {};
    return DeliveryConfig(
      enabled: _bool(json['enabled']),
      minAppVersion: _string(json['min_app_version']) ?? '1.1.0',
      maxDistanceKm: _double(json['max_distance_km']) ?? 10,
      unpaidTimeoutMinutes: _int(json['unpaid_timeout_minutes'], 20),
      paymentMode: _string(payment['mode']) ?? 'online',
      onlinePaymentAvailable: _bool(payment['online_available']),
      sandbox: _bool(payment['sandbox']),
      channels: _strings(payment['channels']),
      pollSeconds: _int(tracking['poll_seconds'], 5),
      realtime: _bool(tracking['realtime']),
    );
  }

  final bool enabled;
  final String minAppVersion;
  final double maxDistanceKm;
  final int unpaidTimeoutMinutes;

  /// `online` (Pay now after the shop accepts) or `cash` (pay the rider).
  final String paymentMode;
  final bool onlinePaymentAvailable;
  final bool sandbox;
  final List<String> channels;
  final int pollSeconds;
  final bool realtime;

  bool get feeInCash => paymentMode == 'cash';
}

/// `GET /delivery/quote`: an estimate for checkout. The binding fee is
/// frozen when the shop accepts.
class DeliveryQuote {
  const DeliveryQuote({required this.distanceKm, required this.fee, required this.distanceSource});

  factory DeliveryQuote.fromJson(Map<String, dynamic> json) => DeliveryQuote(
    distanceKm: _double(json['distance_km']) ?? 0,
    fee: _int(json['fee']),
    distanceSource: _string(json['distance_source']) ?? 'straight_line',
  );

  final double distanceKm;
  final int fee;
  final String distanceSource;
}

class DeliveryVehicle {
  const DeliveryVehicle({required this.type, this.plateNumber, this.make, this.colour});

  factory DeliveryVehicle.fromJson(Map<String, dynamic> json) => DeliveryVehicle(
    type: _string(json['type']) ?? 'boda_boda',
    plateNumber: _string(json['plate_number']),
    make: _string(json['make']),
    colour: _string(json['colour']),
  );

  final String type;
  final String? plateNumber;
  final String? make;
  final String? colour;
}

class DeliveryDriver {
  const DeliveryDriver({required this.name, this.photoUrl, this.ratingAvg, this.ratingCount = 0, this.phone, this.vehicle});

  factory DeliveryDriver.fromJson(Map<String, dynamic> json) {
    final vehicle = _map(json['vehicle']);
    return DeliveryDriver(
      name: _string(json['name']) ?? '',
      photoUrl: _string(json['photo_url']),
      ratingAvg: _double(json['rating_avg']),
      ratingCount: _int(json['rating_count']),
      phone: _string(json['phone']),
      vehicle: vehicle == null ? null : DeliveryVehicle.fromJson(vehicle),
    );
  }

  final String name;
  final String? photoUrl;
  final double? ratingAvg;
  final int ratingCount;

  /// Only while the job is active (buyer) or before pickup (seller).
  final String? phone;
  final DeliveryVehicle? vehicle;
}

class DeliveryPoint {
  const DeliveryPoint({required this.lat, required this.lng, this.label, this.phone});

  static DeliveryPoint? fromJson(Map<String, dynamic>? json, {String labelKey = 'address'}) {
    if (json == null) return null;
    final lat = _double(json['lat']);
    final lng = _double(json['lng']);
    if (lat == null || lng == null) return null;
    return DeliveryPoint(lat: lat, lng: lng, label: _string(json[labelKey]), phone: _string(json['phone']));
  }

  final double lat;
  final double lng;
  final String? label;
  final String? phone;
}

/// The rider's last known position: from the server's heartbeat
/// (`driver_location`) or the Firebase live stream.
class DriverPosition {
  const DriverPosition({required this.lat, required this.lng, this.at});

  static DriverPosition? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    final lat = _double(map['lat']);
    final lng = _double(map['lng']);
    if (lat == null || lng == null) return null;
    final ts = map['ts'];
    return DriverPosition(
      lat: lat,
      lng: lng,
      at: ts is num ? DateTime.fromMillisecondsSinceEpoch(ts.toInt()) : _date(map['at']),
    );
  }

  final double lat;
  final double lng;
  final DateTime? at;
}

class PaymentAttempt {
  const PaymentAttempt({
    required this.id,
    required this.status,
    required this.amount,
    this.channel,
    this.msisdn,
    this.failureReason,
    this.createdAt,
    this.expiresAt,
  });

  factory PaymentAttempt.fromJson(Map<String, dynamic> json) => PaymentAttempt(
    id: _int(json['id']),
    status: _string(json['status']) ?? 'pending',
    amount: _int(json['amount']),
    channel: _string(json['channel']),
    msisdn: _string(json['msisdn']),
    failureReason: _string(json['failure_reason']),
    createdAt: _date(json['created_at']),
    expiresAt: _date(json['expires_at']),
  );

  final int id;

  /// pending | succeeded | failed
  final String status;
  final int amount;
  final String? channel;

  /// Masked by the server, e.g. `+25575••••456`.
  final String? msisdn;
  final String? failureReason;
  final DateTime? createdAt;
  final DateTime? expiresAt;

  bool get isPending => status == 'pending';
  bool get isFailed => status == 'failed';
}

class PaymentReceipt {
  const PaymentReceipt({required this.amount, this.channel, this.reference, this.paidAt});

  factory PaymentReceipt.fromJson(Map<String, dynamic> json) => PaymentReceipt(
    amount: _int(json['amount']),
    channel: _string(json['channel']),
    reference: _string(json['reference']),
    paidAt: _date(json['paid_at']),
  );

  final int amount;
  final String? channel;
  final String? reference;
  final DateTime? paidAt;
}

/// The buyer's payment card (`payment` in the buyer view).
class DeliveryPaymentState {
  const DeliveryPaymentState({
    required this.mode,
    required this.status,
    required this.amountDue,
    required this.covers,
    required this.goodsPaidTo,
    required this.goodsTotal,
    required this.deliveryFee,
    required this.total,
    required this.onlineAvailable,
    required this.sandbox,
    required this.channels,
    this.deadline,
    this.attempt,
    this.receipt,
  });

  factory DeliveryPaymentState.fromJson(Map<String, dynamic> json) {
    final attempt = _map(json['attempt']);
    final receipt = _map(json['receipt']);
    return DeliveryPaymentState(
      mode: _string(json['mode']) ?? 'online',
      status: _string(json['status']) ?? 'unpaid',
      amountDue: _int(json['amount_due']),
      covers: _strings(json['covers']),
      goodsPaidTo: _string(json['goods_paid_to']) ?? 'shop',
      goodsTotal: _int(json['goods_total']),
      deliveryFee: _int(json['delivery_fee']),
      total: _int(json['total']),
      onlineAvailable: _bool(json['online_available']),
      sandbox: _bool(json['sandbox']),
      channels: _strings(json['channels']),
      deadline: _date(json['deadline']),
      attempt: attempt == null ? null : PaymentAttempt.fromJson(attempt),
      receipt: receipt == null ? null : PaymentReceipt.fromJson(receipt),
    );
  }

  final String mode;
  final String status;

  /// What "Pay now" charges, from the server. 0 when nothing is owed.
  final int amountDue;

  /// Which lines `amountDue` covers, e.g. `["delivery_fee"]`.
  final List<String> covers;

  /// `shop` while the wallet is off: goods are paid to the shop directly.
  final String goodsPaidTo;
  final int goodsTotal;
  final int deliveryFee;
  final int total;
  final bool onlineAvailable;
  final bool sandbox;
  final List<String> channels;
  final DateTime? deadline;
  final PaymentAttempt? attempt;
  final PaymentReceipt? receipt;

  bool get inCash => mode == 'cash';
}

class DeliveryRefund {
  const DeliveryRefund({required this.amount, required this.status});

  final int amount;

  /// pending | refunded
  final String status;
}

class DeliveryActions {
  const DeliveryActions({
    this.canPay = false,
    this.canCancel = false,
    this.canRetry = false,
    this.canSwitchToPickup = false,
    this.canRate = false,
  });

  factory DeliveryActions.fromJson(Map<String, dynamic>? json) => DeliveryActions(
    canPay: _bool(json?['can_pay']),
    canCancel: _bool(json?['can_cancel']),
    canRetry: _bool(json?['can_retry']),
    canSwitchToPickup: _bool(json?['can_switch_to_pickup']),
    canRate: _bool(json?['can_rate']),
  );

  final bool canPay;
  final bool canCancel;
  final bool canRetry;
  final bool canSwitchToPickup;
  final bool canRate;
}

class DeliveryTimelineEntry {
  const DeliveryTimelineEntry({required this.status, this.at, this.by, this.reason});

  factory DeliveryTimelineEntry.fromJson(Map<String, dynamic> json) => DeliveryTimelineEntry(
    status: _string(json['status']) ?? '',
    at: _date(json['at']),
    by: _string(json['by']),
    reason: _string(json['reason']),
  );

  final String status;
  final DateTime? at;

  /// buyer | seller | driver | admin | system
  final String? by;
  final String? reason;
}

/// `GET /delivery/orders/{order}`, buyer or seller view.
class DeliveryTracking {
  const DeliveryTracking({
    required this.id,
    required this.orderId,
    required this.orderCode,
    required this.status,
    required this.isActive,
    required this.actions,
    required this.timeline,
    this.distanceKm,
    this.fee,
    this.paymentStatus,
    this.amountDue = 0,
    this.payCashToDriver = false,
    this.deliveryPin,
    this.pinLocked = false,
    this.dispatchWave,
    this.pickup,
    this.dropoff,
    this.payment,
    this.driver,
    this.driverLocation,
    this.refund,
    this.pickupCode,
    this.cancelledBy,
    this.cancelReason,
    this.failureReason,
    this.createdAt,
  });

  factory DeliveryTracking.fromJson(Map<String, dynamic> json) {
    final payment = _map(json['payment']);
    final driver = _map(json['driver']);
    final refund = _map(json['refund']);
    final timeline = json['timeline'];
    return DeliveryTracking(
      id: _int(json['id']),
      orderId: _int(json['order_id']),
      orderCode: _string(json['order_code']) ?? '',
      status: _string(json['status']) ?? 'pending_payment',
      isActive: _bool(json['is_active']),
      distanceKm: _double(json['distance_km']),
      fee: _intOrNull(json['fee']),
      paymentStatus: _string(json['payment_status']),
      amountDue: _int(json['amount_due']),
      payCashToDriver: _bool(json['pay_cash_to_driver']),
      deliveryPin: _string(json['delivery_pin']),
      pinLocked: _bool(json['pin_locked']),
      dispatchWave: _intOrNull(json['dispatch_wave']),
      pickup: DeliveryPoint.fromJson(_map(json['pickup']), labelKey: 'shop_name'),
      dropoff: DeliveryPoint.fromJson(_map(json['dropoff'])),
      payment: payment == null ? null : DeliveryPaymentState.fromJson(payment),
      driver: driver == null ? null : DeliveryDriver.fromJson(driver),
      driverLocation: DriverPosition.fromJson(json['driver_location']),
      refund: refund == null ? null : DeliveryRefund(amount: _int(refund['amount']), status: _string(refund['status']) ?? 'pending'),
      pickupCode: _string(json['pickup_code']),
      actions: DeliveryActions.fromJson(_map(json['actions'])),
      timeline: timeline is List
          ? [for (final e in timeline) if (e is Map) DeliveryTimelineEntry.fromJson(e.cast<String, dynamic>())]
          : const [],
      cancelledBy: _string(json['cancelled_by']),
      cancelReason: _string(json['cancel_reason']),
      failureReason: _string(json['failure_reason']),
      createdAt: _date(json['created_at']),
    );
  }

  final int id;
  final int orderId;
  final String orderCode;

  /// pending_payment | pending_driver | no_driver_found | driver_assigned |
  /// arrived_at_shop | picked_up | on_the_way | arrived | delivered |
  /// cancelled | failed
  final String status;
  final bool isActive;
  final double? distanceKm;
  final int? fee;
  final String? paymentStatus;
  final int amountDue;
  final bool payCashToDriver;
  final String? deliveryPin;
  final bool pinLocked;
  final int? dispatchWave;
  final DeliveryPoint? pickup;
  final DeliveryPoint? dropoff;
  final DeliveryPaymentState? payment;
  final DeliveryDriver? driver;
  final DriverPosition? driverLocation;
  final DeliveryRefund? refund;

  /// Seller view only, while the rider is coming or at the shop.
  final String? pickupCode;
  final DeliveryActions actions;
  final List<DeliveryTimelineEntry> timeline;
  final String? cancelledBy;
  final String? cancelReason;
  final String? failureReason;
  final DateTime? createdAt;

  static const terminalStatuses = {'delivered', 'cancelled'};
  static const inTransitStatuses = {'picked_up', 'on_the_way', 'arrived'};

  bool get isTerminal => terminalStatuses.contains(status);
  bool get isInTransit => inTransitStatuses.contains(status);
  bool get awaitingPayment => status == 'pending_payment';

  /// The rider cancelled before pickup and dispatch started again.
  bool get driverJustCancelled {
    if (status != 'pending_driver' || timeline.isEmpty) return false;
    final last = timeline.last;
    return last.status == 'pending_driver' && last.by == 'driver';
  }

  /// When [status] was reached most recently, from the shared timeline.
  DateTime? reachedAt(String s) {
    for (final entry in timeline.reversed) {
      if (entry.status == s) return entry.at;
    }
    return null;
  }
}

/// `POST /delivery/realtime-token` when Firebase is configured.
class RealtimeCredentials {
  const RealtimeCredentials({required this.token, required this.databaseUrl});

  final String token;
  final String databaseUrl;
}

/// A delivery action the server refused, with its stable `code`
/// (docs/delivery/API.md: "The apps switch on `code`").
class DeliveryActionError implements Exception {
  const DeliveryActionError({required this.code, required this.message, this.statusCode});

  final String code;
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
