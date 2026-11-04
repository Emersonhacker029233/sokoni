import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/features/delivery/presentation/delivery_labels.dart';
import 'package:sokoni/features/delivery/providers/delivery_providers.dart';

import '../../fixtures/delivery_fixtures.dart';

/// Sokoni 1.1 delivery models parse exactly what
/// api/tests/Feature/Delivery/CustomerAppContractTest.php pins, including
/// the decimals Laravel sends as strings and the blocks only one viewer gets.
void main() {
  group('DeliveryTracking (buyer view)', () {
    test('reads every block the tracking screen uses', () {
      final t = DeliveryTracking.fromJson(buyerView(status: 'on_the_way', isActive: true));

      expect(t.status, 'on_the_way');
      expect(t.isActive, isTrue);
      expect(t.distanceKm, 4.23); // sent as "4.23"
      expect(t.fee, 4500);
      expect(t.deliveryPin, '4821');
      expect(t.driver!.name, 'Asha');
      expect(t.driver!.phone, '+255754000000');
      expect(t.driver!.vehicle!.plateNumber, 'MC123ABC');
      expect(t.driverLocation!.lat, -6.80);
      expect(t.pickup!.label, 'Duka la Juma');
      expect(t.dropoff!.phone, '+255765000123');
      expect(t.payment!.goodsTotal, 20000);
      expect(t.payment!.deliveryFee, 4500);
      expect(t.payment!.total, 24500);
      expect(t.payment!.receipt!.amount, 4500);
      expect(t.isInTransit, isTrue);
      expect(t.reachedAt('picked_up'), isNotNull);
    });

    test('pending payment carries the amount due, the deadline and the attempt', () {
      final t = DeliveryTracking.fromJson(buyerView(status: 'pending_payment', attempt: {
        'id': 7, 'status': 'pending', 'amount': 4500, 'channel': 'mpesa', 'msisdn': '+25575••••456',
        'failure_reason': null, 'created_at': '2026-11-04T08:00:00Z', 'expires_at': '2026-11-04T08:02:00Z',
      }));

      expect(t.awaitingPayment, isTrue);
      expect(t.amountDue, 4500);
      expect(t.deliveryPin, isNull);
      expect(t.payment!.deadline, isNotNull);
      expect(t.payment!.attempt!.isPending, isTrue);
      expect(t.payment!.attempt!.msisdn, '+25575••••456');
      expect(t.actions.canPay, isTrue);
    });

    test('a rider who cancelled before pickup is recognised from the timeline', () {
      final t = DeliveryTracking.fromJson(buyerView(status: 'pending_driver', timeline: [
        {'status': 'pending_payment', 'at': '2026-11-04T08:00:00Z', 'by': 'system', 'reason': null},
        {'status': 'pending_driver', 'at': '2026-11-04T08:01:00Z', 'by': 'system', 'reason': null},
        {'status': 'driver_assigned', 'at': '2026-11-04T08:02:00Z', 'by': 'driver', 'reason': null},
        {'status': 'pending_driver', 'at': '2026-11-04T08:05:00Z', 'by': 'driver', 'reason': null},
      ]));

      expect(t.driverJustCancelled, isTrue);
      expect(deliveryStageIndex(t), 0);
    });
  });

  test('seller view has the pickup code and none of the buyer-only blocks', () {
    final t = DeliveryTracking.fromJson(sellerView(status: 'arrived_at_shop'));

    expect(t.pickupCode, 'CD34');
    expect(t.deliveryPin, isNull);
    expect(t.payment, isNull);
    expect(t.dropoff, isNull);
    expect(t.fee, isNull);
    expect(t.driver!.phone, '+255754000000');
  });

  test('the Firebase live position reads lat/lng and a millisecond timestamp', () {
    final p = DriverPosition.fromJson({'lat': -6.8, 'lng': 39.27, 'heading': 90, 'ts': 1762243200000});
    expect(p!.lat, -6.8);
    expect(p.at, DateTime.fromMillisecondsSinceEpoch(1762243200000));
    expect(DriverPosition.fromJson(null), isNull);
    expect(DriverPosition.fromJson({'lat': 'x'}), isNull);
  });

  group('the 1.1 gate', () {
    test('compares versions numerically', () {
      expect(isVersionAtLeast('1.1.0', '1.1.0'), isTrue);
      expect(isVersionAtLeast('1.1.0+9', '1.1.0'), isTrue);
      expect(isVersionAtLeast('1.10.0', '1.9.3'), isTrue);
      expect(isVersionAtLeast('1.0.0', '1.1.0'), isFalse);
      expect(isVersionAtLeast('1.0.9', '1.1'), isFalse);
      expect(isVersionAtLeast('2.0', '1.1.0'), isTrue);
    });

    test('polls fast while paying or moving, and stops when the delivery ends', () {
      expect(DeliveryTrackingController.pollInterval(null), const Duration(seconds: 8));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'on_the_way', isActive: true))), const Duration(seconds: 4));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'pending_payment', attempt: {'id': 1, 'status': 'pending', 'amount': 1}))), const Duration(seconds: 3));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'delivered'))), isNull);
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'cancelled'))), isNull);
    });

    test('idempotency keys are long and unique', () {
      final keys = {for (var i = 0; i < 50; i++) newIdempotencyKey()};
      expect(keys, hasLength(50));
      expect(keys.every((k) => k.length == 24 && RegExp(r'^[a-z0-9]+$').hasMatch(k)), isTrue);
    });
  });

  group('every delivery state has its own English and Swahili text', () {
    final en = lookupAppLocalizations(const Locale('en'));
    final sw = lookupAppLocalizations(const Locale('sw'));

    test('progress steps and seller status lines', () {
      for (final status in [...deliveryStages, 'no_driver_found', 'failed', 'cancelled']) {
        expect(deliveryStageLabel(en, status), isNot(deliveryStageLabel(sw, status)), reason: status);
      }
      for (final status in ['pending_payment', 'pending_driver', 'no_driver_found', 'driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered', 'failed', 'cancelled']) {
        expect(sellerDeliveryStatusLabel(en, status), isNot(sellerDeliveryStatusLabel(sw, status)), reason: status);
      }
    });

    test('cancellation and failure messages', () {
      DeliveryTracking cancelled(String by, {String? reason, String? failure}) => DeliveryTracking.fromJson(
        buyerView(status: 'cancelled')..addAll({'cancelled_by': by, 'cancel_reason': reason, 'failure_reason': failure}),
      );

      final timeout = cancelledMessage(en, cancelled('system', reason: 'payment_timeout'), unpaidTimeoutMinutes: 20);
      expect(timeout, contains('20'));
      expect(cancelledMessage(sw, cancelled('system', reason: 'payment_timeout'), unpaidTimeoutMinutes: 20), contains('20'));
      expect(cancelledMessage(en, cancelled('seller'), unpaidTimeoutMinutes: 20), en.deliveryCancelledBySeller);
      expect(cancelledMessage(en, cancelled('buyer'), unpaidTimeoutMinutes: 20), en.deliveryCancelledByBuyer);
      expect(cancelledMessage(en, cancelled('driver', failure: 'customer_unreachable'), unpaidTimeoutMinutes: 20), en.deliveryUnreachableBody);
      for (final reason in ['vehicle_breakdown', 'accident', 'item_damaged', 'wrong_address', 'other']) {
        expect(failureReasonLabel(en, reason), isNot(failureReasonLabel(sw, reason)), reason: reason);
      }
      expect(refundMessage(en, const DeliveryRefund(amount: 4500, status: 'pending')), contains('TSh 4,500'));
      expect(refundMessage(sw, const DeliveryRefund(amount: 4500, status: 'pending')), contains('TSh 4,500'));
      expect(refundMessage(en, null), isNull);
    });

    test('payment failures, channels and vehicles', () {
      expect(paymentFailureLabel(en, 'declined'), isNot(paymentFailureLabel(en, 'timeout')));
      expect(paymentFailureLabel(sw, 'timeout'), isNot(paymentFailureLabel(en, 'timeout')));
      expect(paymentChannelLabel(en, 'mixx'), 'Mixx by Yas');
      expect(vehicleSummary(en, const DeliveryVehicle(type: 'boda_boda', plateNumber: 'MC123ABC', make: 'TVS', colour: 'Red')), 'Boda boda · Red TVS · MC123ABC');
      expect(vehicleTypeLabel(sw, 'car'), 'Gari');
    });
  });
}
