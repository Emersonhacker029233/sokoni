import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/delivery.dart';

/// The customer-facing progress steps of a Sokoni delivery, in order.
/// `pending_payment` and the problem states are shown separately.
const deliveryStages = ['pending_driver', 'driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered'];

String deliveryStageLabel(AppLocalizations l10n, String status) => switch (status) {
  'pending_driver' => l10n.deliveryStageFinding,
  'driver_assigned' => l10n.deliveryStageAssigned,
  'arrived_at_shop' => l10n.deliveryStageAtShop,
  'picked_up' => l10n.deliveryStagePickedUp,
  'on_the_way' => l10n.deliveryStageOnTheWay,
  'arrived' => l10n.deliveryStageArrived,
  'delivered' => l10n.deliveryStageDelivered,
  'no_driver_found' => l10n.deliveryNoDriverTitle,
  'failed' => l10n.deliveryFailedTitle,
  'cancelled' => l10n.deliveryCancelledTitle,
  _ => l10n.deliveryPayTitle,
};

/// Index into [deliveryStages] the job has reached, or -1 before dispatch.
int deliveryStageIndex(DeliveryTracking tracking) {
  final direct = deliveryStages.indexOf(tracking.status);
  if (direct >= 0) return direct;
  // A problem state keeps the furthest step actually reached.
  for (var i = deliveryStages.length - 1; i >= 0; i--) {
    if (tracking.reachedAt(deliveryStages[i]) != null) return i;
  }
  return -1;
}

String vehicleTypeLabel(AppLocalizations l10n, String type) => switch (type) {
  'bajaj' => l10n.deliveryVehicleBajaj,
  'car' => l10n.deliveryVehicleCar,
  'bicycle' => l10n.deliveryVehicleBicycle,
  _ => l10n.deliveryVehicleBodaBoda,
};

/// "Boda boda · Red TVS · MC123ABC", skipping what the rider didn't fill in.
String vehicleSummary(AppLocalizations l10n, DeliveryVehicle vehicle) => [
  vehicleTypeLabel(l10n, vehicle.type),
  [vehicle.colour, vehicle.make].whereType<String>().where((s) => s.isNotEmpty).join(' '),
  vehicle.plateNumber ?? '',
].where((s) => s.isNotEmpty).join(' · ');

String paymentChannelLabel(AppLocalizations l10n, String channel) => switch (channel) {
  'airtel' => l10n.deliveryChannelAirtel,
  'mixx' => l10n.deliveryChannelMixx,
  'halopesa' => l10n.deliveryChannelHalopesa,
  _ => l10n.deliveryChannelMpesa,
};

String paymentFailureLabel(AppLocalizations l10n, String? reason) => switch (reason) {
  'declined' => l10n.deliveryPayDeclined,
  'timeout' => l10n.deliveryPayExpired,
  _ => l10n.deliveryPayFailed,
};

String failureReasonLabel(AppLocalizations l10n, String? reason) => switch (reason) {
  'vehicle_breakdown' => l10n.deliveryFailureVehicleBreakdown,
  'accident' => l10n.deliveryFailureAccident,
  'item_damaged' => l10n.deliveryFailureItemDamaged,
  'wrong_address' => l10n.deliveryFailureWrongAddress,
  _ => l10n.deliveryFailureOther,
};

/// Why a delivery ended without arriving, for the buyer.
String cancelledMessage(AppLocalizations l10n, DeliveryTracking tracking, {required int unpaidTimeoutMinutes}) {
  if (tracking.cancelReason == 'payment_timeout') return l10n.deliveryCancelledTimeout(unpaidTimeoutMinutes);
  if (tracking.failureReason == 'customer_unreachable') return l10n.deliveryUnreachableBody;
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

/// One line telling the seller what is happening with the rider.
String sellerDeliveryStatusLabel(AppLocalizations l10n, String status) => switch (status) {
  'pending_payment' => l10n.sellerDeliveryPendingPayment,
  'pending_driver' => l10n.sellerDeliveryFindingRider,
  'no_driver_found' => l10n.sellerDeliveryNoRider,
  'driver_assigned' => l10n.sellerDeliveryRiderComing,
  'arrived_at_shop' => l10n.sellerDeliveryRiderAtShop,
  'picked_up' || 'on_the_way' || 'arrived' => l10n.sellerDeliveryInTransit,
  'delivered' => l10n.sellerDeliveryDelivered,
  'failed' => l10n.sellerDeliveryFailed,
  _ => l10n.sellerDeliveryCancelled,
};

/// The server's own (already localised) message for a refused action,
/// falling back to a generic line.
String deliveryErrorMessage(AppLocalizations l10n, Object error) {
  if (error is DeliveryActionError) return error.message;
  return '$error';
}
