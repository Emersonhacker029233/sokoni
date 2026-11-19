// Bolt delivery and returns models (docs/delivery/BOLT.md, RETURNS.md).
//
// Hand-written rather than freezed: the server sends some numbers as
// strings (Laravel decimal casts) and several blocks only to one viewer
// (the buyer gets `payment` and `dropoff`; the seller gets `fee_paid`), so
// every field is parsed defensively here. Every amount in these classes is
// a number the server stored; the app never derives a fee, a total or a
// payment status of its own.

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

/// `GET /features`: what this signed-in user may see. Everything false for
/// a guest, outside the dark launch, or when the server predates it.
class SokoniFeatures {
  const SokoniFeatures({
    this.deliveryEnabled = false,
    this.returnsEnabled = false,
    this.returnsTermsVersion,
    this.returnsTermsAccepted = false,
  });

  factory SokoniFeatures.fromJson(Map<String, dynamic> json) => SokoniFeatures(
    deliveryEnabled: _bool(json['delivery_enabled']),
    returnsEnabled: _bool(json['returns_enabled']),
    returnsTermsVersion: _string(json['returns_terms_version']),
    returnsTermsAccepted: _bool(json['returns_terms_accepted']),
  );

  static const off = SokoniFeatures();

  final bool deliveryEnabled;
  final bool returnsEnabled;

  /// The Terms version with the Return Policy section (1.2), while returns are on.
  final String? returnsTermsVersion;
  final bool returnsTermsAccepted;
}

/// `GET /delivery/config`.
class DeliveryConfig {
  const DeliveryConfig({
    required this.enabled,
    required this.minAppVersion,
    required this.maxDistanceKm,
    required this.unpaidTimeoutMinutes,
    required this.onlinePaymentAvailable,
    required this.sandbox,
    required this.channels,
    required this.pollSeconds,
    this.returnsEnabled = false,
  });

  factory DeliveryConfig.fromJson(Map<String, dynamic> json) {
    final payment = _map(json['payment']) ?? const {};
    final tracking = _map(json['tracking']) ?? const {};
    return DeliveryConfig(
      enabled: _bool(json['enabled']),
      minAppVersion: _string(json['min_app_version']) ?? '1.1.0',
      maxDistanceKm: _double(json['max_distance_km']) ?? 10,
      unpaidTimeoutMinutes: _int(json['unpaid_timeout_minutes'], 20),
      onlinePaymentAvailable: _bool(payment['online_available']),
      sandbox: _bool(payment['sandbox']),
      channels: _strings(payment['channels']),
      pollSeconds: _int(tracking['poll_seconds'], 10),
      returnsEnabled: _bool(json['returns_enabled']),
    );
  }

  final bool enabled;
  final String minAppVersion;
  final double maxDistanceKm;
  final int unpaidTimeoutMinutes;
  final bool onlinePaymentAvailable;
  final bool sandbox;
  final List<String> channels;
  final int pollSeconds;
  final bool returnsEnabled;
}

/// A shop's return policy: `none`, `24h`, `3d` or `7d`, with the label in
/// the user's language and the shop's own notes.
class ReturnPolicyInfo {
  const ReturnPolicyInfo({required this.code, required this.hours, required this.label, this.notes});

  static ReturnPolicyInfo? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    return ReturnPolicyInfo(
      code: _string(map['code']) ?? 'none',
      hours: _int(map['hours']),
      label: _string(map['label']) ?? '',
      notes: _string(map['notes']),
    );
  }

  final String code;
  final int hours;
  final String label;
  final String? notes;

  bool get acceptsReturns => hours > 0;
}

/// `GET /delivery/quote`: the fixed delivery fee for checkout. It is
/// confirmed when the shop accepts and never changes after that. Bolt's own
/// prices never reach the app.
class DeliveryQuote {
  const DeliveryQuote({
    required this.fee,
    required this.distanceKm,
    this.category,
    this.etaSeconds,
    this.returnPolicy,
  });

  factory DeliveryQuote.fromJson(Map<String, dynamic> json) => DeliveryQuote(
    fee: _int(json['fee']),
    distanceKm: _double(json['distance_km']) ?? 0,
    category: _string(json['category']),
    etaSeconds: _intOrNull(json['eta_seconds']),
    returnPolicy: ReturnPolicyInfo.fromJson(json['return_policy']),
  );

  final int fee;
  final double distanceKm;
  final String? category;

  /// How far away the nearest Bolt driver is, in seconds.
  final int? etaSeconds;
  final ReturnPolicyInfo? returnPolicy;
}

/// The Bolt driver on the job, as Bolt reported them.
class BoltDriver {
  const BoltDriver({this.name, this.phone, this.plate, this.model, this.photoUrl, this.etaSeconds});

  static BoltDriver? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    return BoltDriver(
      name: _string(map['name']),
      phone: _string(map['phone']),
      plate: _string(map['plate']),
      model: _string(map['model']),
      photoUrl: _string(map['photo_url']),
      etaSeconds: _intOrNull(map['eta_seconds']),
    );
  }

  final String? name;

  /// Only while the driver is on the job (buyer) or before pickup (seller).
  final String? phone;
  final String? plate;
  final String? model;
  final String? photoUrl;
  final int? etaSeconds;
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

  static PaymentAttempt? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    return PaymentAttempt(
      id: _int(map['id']),
      status: _string(map['status']) ?? 'pending',
      amount: _int(map['amount']),
      channel: _string(map['channel']),
      msisdn: _string(map['msisdn']),
      failureReason: _string(map['failure_reason']),
      createdAt: _date(map['created_at']),
      expiresAt: _date(map['expires_at']),
    );
  }

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

  static PaymentReceipt? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    return PaymentReceipt(
      amount: _int(map['amount']),
      channel: _string(map['channel']),
      reference: _string(map['reference']),
      paidAt: _date(map['paid_at']),
    );
  }

  final int amount;
  final String? channel;
  final String? reference;
  final DateTime? paidAt;
}

/// The buyer's payment card (`payment` in the buyer view).
class DeliveryPaymentState {
  const DeliveryPaymentState({
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

  factory DeliveryPaymentState.fromJson(Map<String, dynamic> json) => DeliveryPaymentState(
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
    attempt: PaymentAttempt.fromJson(json['attempt']),
    receipt: PaymentReceipt.fromJson(json['receipt']),
  );

  final String status;

  /// What "Pay now" charges, from the server. 0 when nothing is owed.
  final int amountDue;

  /// Which lines `amountDue` covers, e.g. `["delivery_fee"]`.
  final List<String> covers;

  /// `shop`: goods are paid to the shop directly.
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
}

class DeliveryRefund {
  const DeliveryRefund({required this.amount, required this.status});

  final int amount;

  /// pending | refunded
  final String status;
}

class DeliveryActions {
  const DeliveryActions({this.canPay = false, this.canCancel = false, this.canRetry = false, this.canSwitchToPickup = false});

  factory DeliveryActions.fromJson(Map<String, dynamic>? json) => DeliveryActions(
    canPay: _bool(json?['can_pay']),
    canCancel: _bool(json?['can_cancel']),
    canRetry: _bool(json?['can_retry']),
    canSwitchToPickup: _bool(json?['can_switch_to_pickup']),
  );

  final bool canPay;
  final bool canCancel;
  final bool canRetry;
  final bool canSwitchToPickup;
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

  /// buyer | seller | admin | system | provider (Bolt)
  final String? by;
  final String? reason;
}

/// The Bolt ride that brings a returned item back (customer to shop).
class ReturnRide {
  const ReturnRide({required this.status, this.failureReason, this.driver, this.trackingUrl});

  static ReturnRide? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    return ReturnRide(
      status: _string(map['status']) ?? '',
      failureReason: _string(map['failure_reason']),
      driver: BoltDriver.fromJson(map['driver']),
      trackingUrl: _string(map['tracking_url']),
    );
  }

  /// pending_quote | quoted | unavailable | pending_driver | driver_assigned |
  /// at_pickup | on_the_way | delivered | no_driver_found | failed | cancelled
  final String status;
  final String? failureReason;
  final BoltDriver? driver;
  final String? trackingUrl;

  bool get isActive => const {'pending_driver', 'driver_assigned', 'at_pickup', 'on_the_way'}.contains(status);
}

class ReturnActions {
  const ReturnActions({
    this.canAccept = false,
    this.canReject = false,
    this.canConfirmRefund = false,
    this.canReport = false,
    this.canDispute = false,
    this.canWithdraw = false,
    this.canPay = false,
    this.canBookRide = false,
  });

  factory ReturnActions.fromJson(Map<String, dynamic>? json) => ReturnActions(
    canAccept: _bool(json?['can_accept']),
    canReject: _bool(json?['can_reject']),
    canConfirmRefund: _bool(json?['can_confirm_refund']),
    canReport: _bool(json?['can_report']),
    canDispute: _bool(json?['can_dispute']),
    canWithdraw: _bool(json?['can_withdraw']),
    canPay: _bool(json?['can_pay']),
    canBookRide: _bool(json?['can_book_ride']),
  );

  final bool canAccept;
  final bool canReject;
  final bool canConfirmRefund;
  final bool canReport;
  final bool canDispute;
  final bool canWithdraw;
  final bool canPay;
  final bool canBookRide;
}

/// `GET /returns/{id}` (and the `request` inside an order's `return`).
class ReturnRequestInfo {
  const ReturnRequestInfo({
    required this.id,
    required this.orderId,
    required this.status,
    required this.reason,
    required this.reasonLabel,
    required this.photos,
    required this.payer,
    required this.actions,
    this.comment,
    this.contentHidden = false,
    this.handledBySupport = false,
    this.fee,
    this.feeStatus,
    this.sellerDeadline,
    this.decisionBy,
    this.decisionNote,
    this.disputeComment,
    this.resolutionNote,
    this.ride,
    this.amountDue = 0,
    this.paymentAttempt,
    this.closedReason,
    this.createdAt,
  });

  factory ReturnRequestInfo.fromJson(Map<String, dynamic> json) {
    final decision = _map(json['decision']);
    final dispute = _map(json['dispute']);
    final payment = _map(json['payment']);
    final photos = json['photos'];
    return ReturnRequestInfo(
      id: _int(json['id']),
      orderId: _int(json['order_id']),
      status: _string(json['status']) ?? 'requested',
      reason: _string(json['reason']) ?? '',
      reasonLabel: _string(json['reason_label']) ?? '',
      comment: _string(json['comment']),
      photos: photos is List ? [for (final p in photos) if (_map(p)?['url'] case final String url) url] : const [],
      contentHidden: _bool(json['content_hidden']),
      handledBySupport: _bool(json['handled_by_support']),
      payer: _string(json['payer']) ?? 'seller',
      fee: _intOrNull(json['fee']),
      feeStatus: _string(json['fee_status']),
      sellerDeadline: _date(json['seller_deadline']),
      decisionBy: _string(decision?['by']),
      decisionNote: _string(decision?['note']),
      disputeComment: _string(dispute?['comment']),
      resolutionNote: _string(json['resolution_note']),
      ride: ReturnRide.fromJson(json['ride']),
      amountDue: _int(payment?['amount_due']),
      paymentAttempt: PaymentAttempt.fromJson(payment?['attempt']),
      closedReason: _string(json['closed_reason']),
      createdAt: _date(json['created_at']),
      actions: ReturnActions.fromJson(_map(json['actions'])),
    );
  }

  final int id;
  final int orderId;

  /// requested | accepted | rejected | disputed | return_in_transit | received | refunded | closed
  final String status;
  final String reason;
  final String reasonLabel;
  final String? comment;

  /// Signed links, valid for 30 minutes.
  final List<String> photos;
  final bool contentHidden;
  final bool handledBySupport;

  /// buyer | seller: who pays the Bolt ride back.
  final String payer;
  final int? fee;

  /// unpaid | paid | owed_by_seller | waived
  final String? feeStatus;
  final DateTime? sellerDeadline;
  final String? decisionBy;
  final String? decisionNote;
  final String? disputeComment;
  final String? resolutionNote;
  final ReturnRide? ride;
  final int amountDue;
  final PaymentAttempt? paymentAttempt;
  final String? closedReason;
  final DateTime? createdAt;
  final ReturnActions actions;
}

/// An order's `return` block: the policy seen at checkout, the deadline,
/// and the request once there is one. Only for users inside the returns launch.
class OrderReturnInfo {
  const OrderReturnInfo({required this.policy, required this.canRequest, this.eligibleUntil, this.request});

  static OrderReturnInfo? fromJson(Object? json) {
    final map = _map(json);
    if (map == null) return null;
    final request = _map(map['request']);
    return OrderReturnInfo(
      policy: ReturnPolicyInfo.fromJson(map['policy']) ?? const ReturnPolicyInfo(code: 'none', hours: 0, label: ''),
      canRequest: _bool(map['can_request']),
      eligibleUntil: _date(map['eligible_until']),
      request: request == null ? null : ReturnRequestInfo.fromJson(request),
    );
  }

  final ReturnPolicyInfo policy;
  final bool canRequest;
  final DateTime? eligibleUntil;
  final ReturnRequestInfo? request;
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
    this.feePaid = false,
    this.pickup,
    this.dropoff,
    this.payment,
    this.driver,
    this.trackingUrl,
    this.refund,
    this.returnInfo,
    this.cancelledBy,
    this.cancelReason,
    this.failureReason,
    this.createdAt,
  });

  factory DeliveryTracking.fromJson(Map<String, dynamic> json) {
    final payment = _map(json['payment']);
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
      feePaid: _bool(json['fee_paid']) || _string(json['payment_status']) == 'paid',
      pickup: DeliveryPoint.fromJson(_map(json['pickup']), labelKey: 'shop_name'),
      dropoff: DeliveryPoint.fromJson(_map(json['dropoff'])),
      payment: payment == null ? null : DeliveryPaymentState.fromJson(payment),
      driver: BoltDriver.fromJson(json['driver']),
      trackingUrl: _string(json['tracking_url']),
      refund: refund == null ? null : DeliveryRefund(amount: _int(refund['amount']), status: _string(refund['status']) ?? 'pending'),
      returnInfo: OrderReturnInfo.fromJson(json['return']),
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

  /// pending_quote | pending_payment | awaiting_ready | pending_driver |
  /// driver_assigned | arrived_at_shop | on_the_way | delivered |
  /// no_driver_found | failed | cancelled
  final String status;
  final bool isActive;
  final double? distanceKm;
  /// The fixed delivery fee (buyer view).
  final int? fee;
  final String? paymentStatus;
  final int amountDue;

  /// Seller view: the buyer has paid the delivery fee.
  final bool feePaid;
  final DeliveryPoint? pickup;
  final DeliveryPoint? dropoff;
  final DeliveryPaymentState? payment;
  final BoltDriver? driver;

  /// Bolt's live tracking page, only while the ride is running.
  final String? trackingUrl;
  final DeliveryRefund? refund;
  final OrderReturnInfo? returnInfo;
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

  /// Bolt sent the ride back to searching after a driver had taken it.
  bool get driverChanged {
    if (status != 'pending_driver' || timeline.length < 2) return false;
    return timeline[timeline.length - 2].status == 'driver_assigned' || timeline[timeline.length - 2].status == 'arrived_at_shop';
  }

  /// When [s] was reached most recently, from the shared timeline.
  DateTime? reachedAt(String s) {
    for (final entry in timeline.reversed) {
      if (entry.status == s) return entry.at;
    }
    return null;
  }
}

/// A delivery or return action the server refused, with its stable `code`
/// (the apps switch on `code`; `message` is already in the user's language).
class DeliveryActionError implements Exception {
  const DeliveryActionError({required this.code, required this.message, this.statusCode});

  final String code;
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
