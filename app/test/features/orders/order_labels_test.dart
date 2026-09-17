import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/features/orders/presentation/order_labels.dart';

/// Language audit (client feedback): order.status/deliveryMethod/
/// paymentMethod are the server's raw enum values — every place that
/// used to render one of these directly (Text(order.status) and
/// friends) showed English regardless of the app's locale. These
/// helpers are what every one of those call sites now goes through.
void main() {
  final en = lookupAppLocalizations(const Locale('en'));
  final sw = lookupAppLocalizations(const Locale('sw'));

  group('orderStatusLabel', () {
    for (final status in ['pending', 'accepted', 'ready', 'completed', 'cancelled']) {
      test('"$status" has a real, non-empty label in both languages', () {
        expect(orderStatusLabel(en, status), isNotEmpty);
        expect(orderStatusLabel(sw, status), isNotEmpty);
        expect(orderStatusLabel(en, status), isNot(equals(orderStatusLabel(sw, status))));
      });
    }

    test('an unrecognised status falls back to the pending label rather than throwing', () {
      expect(orderStatusLabel(en, 'some_future_status'), en.orderStatusPending);
    });
  });

  group('orderDeliveryMethodLabel', () {
    test('pickup and delivery each have their own distinct label', () {
      expect(orderDeliveryMethodLabel(en, 'pickup'), en.checkoutPickup);
      expect(orderDeliveryMethodLabel(en, 'delivery'), en.checkoutDelivery);
      expect(orderDeliveryMethodLabel(en, 'pickup'), isNot(equals(orderDeliveryMethodLabel(en, 'delivery'))));
    });
  });

  group('orderPaymentMethodLabel', () {
    test('cash-on-delivery and pay-on-pickup each have their own distinct label', () {
      expect(orderPaymentMethodLabel(en, 'cash_on_delivery'), en.checkoutCashOnDelivery);
      expect(orderPaymentMethodLabel(en, 'pay_on_pickup'), en.checkoutPayOnPickup);
    });
  });
}
