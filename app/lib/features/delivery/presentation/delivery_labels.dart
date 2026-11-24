import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/delivery.dart';

/// The customer-facing steps of a Bolt delivery, in order. Pricing,
/// payment and the problem states are shown separately.
const deliveryStages = ['awaiting_ready', 'pending_driver', 'driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered'];

String deliveryStageLabel(AppLocalizations l10n, String status) => switch (status) {
  'awaiting_ready' => l10n.deliveryStageShopPacking,
  'pending_driver' => l10n.deliveryStageFinding,
  'driver_assigned' => l10n.deliveryStageAssigned,
  'arrived_at_shop' => l10n.deliveryStageAtShop,
  'on_the_way' => l10n.deliveryStageOnTheWay,
  'delivered' => l10n.deliveryStageDelivered,
  'no_driver_found' => l10n.deliveryNoDriverTitle,
  'failed' => l10n.deliveryFailedTitle,
  'cancelled' => l10n.deliveryCancelledTitle,
  _ => l10n.deliveryPayTitle,
};

/// Index into [deliveryStages] the delivery has reached, or -1 before payment.
int deliveryStageIndex(DeliveryTracking tracking) {
  final direct = deliveryStages.indexOf(tracking.status);
  if (direct >= 0) return direct;
  // A problem state keeps the furthest step actually reached.
  for (var i = deliveryStages.length - 1; i >= 0; i--) {
    if (tracking.reachedAt(deliveryStages[i]) != null) return i;
  }
  return -1;
}

/// "Toyota IST · T123ABC", skipping what Bolt didn't send.
String boltVehicleSummary(BoltDriver driver) =>
    [driver.model ?? '', driver.plate ?? ''].where((s) => s.isNotEmpty).join(' · ');

/// The mobile-money networks the server offers (its payment gateway's list).
String paymentChannelLabel(AppLocalizations l10n, String channel) => switch (channel) {
  'airtel' => l10n.deliveryChannelAirtel,
  'mixx' => l10n.deliveryChannelMixx,
  'halopesa' => l10n.deliveryChannelHalopesa,
  'azampesa' => l10n.deliveryChannelAzampesa,
  _ => l10n.deliveryChannelMpesa,
};

/// Why an attempt failed, from the server's `failure_reason`. Dalipay gives
/// no reason for a failure (`provider_failed`): "The payment didn't go through."
String paymentFailureLabel(AppLocalizations l10n, String? reason) => switch (reason) {
  'provider_failed' => l10n.deliveryPayFailed,
  'declined' => l10n.deliveryPayDeclined,
  'timeout' => l10n.deliveryPayExpired,
  'invalid_number' => l10n.deliveryPayInvalidNumber,
  'gateway_error' || 'rejected' || 'unsupported_channel' => l10n.deliveryPayServiceError,
  _ => l10n.deliveryPayFailed,
};

/// Why Bolt couldn't take or finish the delivery, for the buyer.
String deliveryProblemBody(AppLocalizations l10n, String? reason) => switch (reason) {
  'area_not_serviced' => l10n.deliveryBoltNotServiced,
  'no_driver_found' || 'no_driver_available' => l10n.deliveryNoDriverBody,
  'shop_no_show' => l10n.deliveryBoltShopNoShow,
  'provider_cancelled_in_transit' || 'client_cancelled_in_transit' => l10n.deliveryBoltCancelledInTransit,
  'booking_failed' || 'quote_failed' || 'payment_booking_failed' => l10n.deliveryBoltBookingFailed,
  _ => l10n.deliveryBoltCancelledByBolt,
};

/// Why a delivery ended without arriving, for the buyer.
String cancelledMessage(AppLocalizations l10n, DeliveryTracking tracking, {required int unpaidTimeoutMinutes}) {
  if (tracking.cancelReason == 'payment_timeout') return l10n.deliveryCancelledTimeout(unpaidTimeoutMinutes);
  return switch (tracking.cancelledBy) {
    'buyer' => l10n.deliveryCancelledByBuyer,
    'seller' => l10n.deliveryCancelledBySeller,
    _ => l10n.deliveryCancelledBySokoni,
  };
}

/// The refund line, from the server's recorded refund.
String? refundMessage(AppLocalizations l10n, DeliveryRefund? refund) {
  if (refund == null || refund.amount <= 0) return null;
  final amount = SokoniFormat.tzs(refund.amount);
  return refund.status == 'refunded' ? l10n.deliveryRefunded(amount) : l10n.deliveryRefundPending(amount);
}

/// One line telling the seller what is happening with the delivery.
String sellerDeliveryStatusLabel(AppLocalizations l10n, DeliveryTracking tracking) => switch (tracking.status) {
  'pending_quote' || 'pending_payment' => l10n.sellerDeliveryPendingPayment,
  'awaiting_ready' => l10n.sellerDeliveryMarkReady,
  'pending_driver' => l10n.sellerDeliveryFindingRider,
  'no_driver_found' => l10n.sellerDeliveryNoRider,
  'driver_assigned' => l10n.sellerDeliveryRiderComing,
  'arrived_at_shop' => l10n.sellerDeliveryRiderAtShop,
  'picked_up' || 'on_the_way' || 'arrived' => l10n.sellerDeliveryInTransit,
  'delivered' => l10n.sellerDeliveryDelivered,
  'failed' => l10n.sellerDeliveryFailed,
  _ => l10n.sellerDeliveryCancelled,
};

/// "Returns within 3 days" for a policy, or "No returns".
String returnPolicyLabel(AppLocalizations l10n, String code) => switch (code) {
  '24h' => l10n.returnPolicy24h,
  '3d' => l10n.returnPolicy3d,
  '7d' => l10n.returnPolicy7d,
  _ => l10n.returnPolicyNone,
};

String returnReasonLabel(AppLocalizations l10n, String reason) => switch (reason) {
  'changed_mind' => l10n.returnReasonChangedMind,
  'wrong_item' => l10n.returnReasonWrongItem,
  'damaged' => l10n.returnReasonDamaged,
  _ => l10n.returnReasonNotAsDescribed,
};

String returnStatusLabel(AppLocalizations l10n, String status) => switch (status) {
  'requested' => l10n.returnStatusRequested,
  'accepted' => l10n.returnStatusAccepted,
  'rejected' => l10n.returnStatusRejected,
  'disputed' => l10n.returnStatusDisputed,
  'return_in_transit' => l10n.returnStatusInTransit,
  'received' => l10n.returnStatusReceived,
  'refunded' => l10n.returnStatusRefunded,
  _ => l10n.returnStatusClosed,
};

/// The server's own (already localised) message for a refused action,
/// falling back to a generic line.
String deliveryErrorMessage(AppLocalizations l10n, Object error) {
  if (error is DeliveryActionError) return error.message;
  return '$error';
}
