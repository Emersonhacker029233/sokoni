import '../../../core/l10n/gen/app_localizations.dart';

/// Language audit (client feedback): `order.status`, `order.deliveryMethod`
/// and `order.paymentMethod` are the server's raw enum values
/// ('pending'/'accepted'/'ready'/'completed'/'cancelled',
/// 'pickup'/'delivery', 'cash_on_delivery'/'pay_on_pickup') — every place
/// that rendered one of these directly (`Text(order.status)` and
/// friends) was showing English regardless of the app's locale, exactly
/// the "mixture of two languages" the Kiswahili-default audit called
/// out. Centralised here rather than repeated per call site, and reusing
/// the existing checkout screen's method labels rather than duplicating
/// them under a new key.
String orderStatusLabel(AppLocalizations l10n, String status) => switch (status) {
  'accepted' => l10n.orderStatusAccepted,
  'ready' => l10n.orderStatusReady,
  'completed' => l10n.orderStatusCompleted,
  'cancelled' => l10n.orderStatusCancelled,
  _ => l10n.orderStatusPending,
};

String orderDeliveryMethodLabel(AppLocalizations l10n, String deliveryMethod) =>
    deliveryMethod == 'delivery' ? l10n.checkoutDelivery : l10n.checkoutPickup;

String orderPaymentMethodLabel(AppLocalizations l10n, String paymentMethod) =>
    paymentMethod == 'pay_on_pickup' ? l10n.checkoutPayOnPickup : l10n.checkoutCashOnDelivery;
