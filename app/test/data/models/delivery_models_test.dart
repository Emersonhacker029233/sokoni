import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/features/delivery/presentation/delivery_labels.dart';
import 'package:sokoni/features/delivery/providers/delivery_providers.dart';

import '../../fixtures/delivery_fixtures.dart';

/// The Bolt delivery and returns models parse exactly what
/// api/tests/Feature/Delivery/CustomerAppContractTest.php pins, including
/// the decimals Laravel sends as strings and the blocks only one viewer gets.
void main() {
  group('DeliveryTracking (buyer view)', () {
    test('reads every block the order screen uses', () {
      final t = DeliveryTracking.fromJson(buyerView(status: 'on_the_way'));

      expect(t.status, 'on_the_way');
      expect(t.isActive, isTrue);
      expect(t.distanceKm, 4.23); // sent as "4.23"
      expect(t.fee, 6400);
      expect(t.estimateMin, 5200);
      expect(t.estimateMax, 6400);
      expect(t.driver!.name, 'Seyf Driver');
      expect(t.driver!.phone, '+255700667788');
      expect(t.driver!.plate, 'T123ABC');
      expect(t.driver!.model, 'Toyota IST');
      expect(t.driver!.etaSeconds, 180);
      expect(t.trackingUrl, 'https://bolt.eu/track/abc');
      expect(t.pickup!.label, 'Duka la Juma');
      expect(t.dropoff!.phone, '+255765000123');
      expect(t.payment!.goodsTotal, 20000);
      expect(t.payment!.deliveryFee, 6400);
      expect(t.payment!.total, 26400);
      expect(t.payment!.receipt!.amount, 6400);
      expect(t.isInTransit, isTrue);
      expect(t.returnInfo, isNull, reason: 'no return block outside the returns launch');
    });

    test('pending payment carries the amount due, the deadline and the attempt', () {
      final t = DeliveryTracking.fromJson(buyerView(status: 'pending_payment', attempt: {
        'id': 7, 'status': 'pending', 'amount': 6400, 'channel': 'mpesa', 'msisdn': '+25575••••456',
        'failure_reason': null, 'created_at': '2026-11-17T08:00:00Z', 'expires_at': '2026-11-17T08:02:00Z',
      }));

      expect(t.awaitingPayment, isTrue);
      expect(t.amountDue, 6400);
      expect(t.driver, isNull);
      expect(t.trackingUrl, isNull);
      expect(t.payment!.deadline, isNotNull);
      expect(t.payment!.attempt!.isPending, isTrue);
      expect(t.payment!.attempt!.msisdn, '+25575••••456');
      expect(t.actions.canPay, isTrue);
    });

    test('Bolt sending the ride back to searching is recognised from the timeline', () {
      final t = DeliveryTracking.fromJson(buyerView(status: 'pending_driver', timeline: [
        {'status': 'awaiting_ready', 'at': '2026-11-17T08:00:00Z', 'by': 'system', 'reason': null},
        {'status': 'pending_driver', 'at': '2026-11-17T08:01:00Z', 'by': 'system', 'reason': null},
        {'status': 'driver_assigned', 'at': '2026-11-17T08:02:00Z', 'by': 'provider', 'reason': null},
        {'status': 'pending_driver', 'at': '2026-11-17T08:05:00Z', 'by': 'provider', 'reason': null},
      ]));

      expect(t.driverChanged, isTrue);
      expect(deliveryStageIndex(t), 1);
    });

    test('the return block: policy, deadline and the request with its actions and ride', () {
      final t = DeliveryTracking.fromJson(buyerView(
        status: 'delivered',
        returnBlock: returnBlockJson(request: returnRequestJson(
          status: 'return_in_transit',
          actions: {'can_book_ride': false, 'can_withdraw': false},
          ride: {'status': 'driver_assigned', 'failure_reason': null, 'driver': boltDriverJson(), 'tracking_url': 'https://bolt.eu/track/r'},
        )),
      ));

      final info = t.returnInfo!;
      expect(info.policy.code, '3d');
      expect(info.policy.acceptsReturns, isTrue);
      expect(info.policy.notes, 'Unused, in the original box.');
      expect(info.eligibleUntil, isNotNull);
      final r = info.request!;
      expect(r.status, 'return_in_transit');
      expect(r.payer, 'seller');
      expect(r.feeStatus, 'owed_by_seller');
      expect(r.photos, hasLength(1));
      expect(r.ride!.isActive, isTrue);
      expect(r.ride!.driver!.plate, 'T123ABC');
      expect(r.ride!.trackingUrl, 'https://bolt.eu/track/r');
    });
  });

  test('seller view has fee_paid and none of the buyer-only blocks', () {
    final t = DeliveryTracking.fromJson(sellerView(status: 'arrived_at_shop'));

    expect(t.feePaid, isTrue);
    expect(t.payment, isNull);
    expect(t.dropoff, isNull);
    expect(t.fee, isNull);
    expect(t.driver!.phone, '+255700667788');
    expect(t.trackingUrl, isNotNull);
  });

  test('features default to off and parse the returns Terms state', () {
    expect(SokoniFeatures.off.deliveryEnabled, isFalse);
    final f = SokoniFeatures.fromJson({'delivery_enabled': true, 'returns_enabled': true, 'returns_terms_version': '1.2', 'returns_terms_accepted': false});
    expect(f.deliveryEnabled, isTrue);
    expect(f.returnsTermsVersion, '1.2');
    expect(f.returnsTermsAccepted, isFalse);
  });

  test('the checkout quote reads the Bolt range, ETA and return policy', () {
    final q = DeliveryQuote.fromJson(quoteJson(returnPolicy: {'code': '7d', 'hours': 168, 'label': 'Returns within 7 days', 'notes': null}));
    expect(q.fee, 6400);
    expect(q.min, 5200);
    expect(q.max, 6400);
    expect(q.etaSeconds, 240);
    expect(q.returnPolicy!.code, '7d');
    expect(DeliveryQuote.fromJson(quoteJson()).returnPolicy, isNull);
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

    test('polls within Bolt fair use while moving, fast while paying, and stops when it all ends', () {
      expect(DeliveryTrackingController.pollInterval(null), const Duration(seconds: 8));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'on_the_way'))), const Duration(seconds: 10));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'pending_payment', attempt: {'id': 1, 'status': 'pending', 'amount': 1}))), const Duration(seconds: 3));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'delivered'))), isNull);
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(buyerView(status: 'cancelled'))), isNull);
      // A delivered order keeps polling while its return is moving.
      final returning = buyerView(status: 'delivered', returnBlock: returnBlockJson(request: returnRequestJson(status: 'return_in_transit')));
      expect(DeliveryTrackingController.pollInterval(DeliveryTracking.fromJson(returning)), const Duration(seconds: 10));
    });

    test('idempotency keys are long and unique', () {
      final keys = {for (var i = 0; i < 50; i++) newIdempotencyKey()};
      expect(keys, hasLength(50));
      expect(keys.every((k) => k.length == 24 && RegExp(r'^[a-z0-9]+$').hasMatch(k)), isTrue);
    });
  });

  group('every delivery and return state has its own English and Swahili text', () {
    final en = lookupAppLocalizations(const Locale('en'));
    final sw = lookupAppLocalizations(const Locale('sw'));

    test('progress steps and seller status lines', () {
      for (final status in [...deliveryStages, 'no_driver_found', 'failed', 'cancelled']) {
        expect(deliveryStageLabel(en, status), isNot(deliveryStageLabel(sw, status)), reason: status);
      }
      for (final status in ['pending_payment', 'awaiting_ready', 'pending_driver', 'no_driver_found', 'driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered', 'failed', 'cancelled']) {
        final view = DeliveryTracking.fromJson(sellerView(status: status));
        expect(sellerDeliveryStatusLabel(en, view), isNot(sellerDeliveryStatusLabel(sw, view)), reason: status);
      }
    });

    test('Bolt problems, cancellations and refunds', () {
      DeliveryTracking cancelled(String by, {String? reason}) => DeliveryTracking.fromJson(
        buyerView(status: 'cancelled')..addAll({'cancelled_by': by, 'cancel_reason': reason}),
      );

      expect(cancelledMessage(en, cancelled('system', reason: 'payment_timeout'), unpaidTimeoutMinutes: 20), contains('20'));
      expect(cancelledMessage(sw, cancelled('system', reason: 'payment_timeout'), unpaidTimeoutMinutes: 20), contains('20'));
      expect(cancelledMessage(en, cancelled('seller'), unpaidTimeoutMinutes: 20), en.deliveryCancelledBySeller);
      expect(cancelledMessage(en, cancelled('buyer'), unpaidTimeoutMinutes: 20), en.deliveryCancelledByBuyer);
      for (final reason in ['area_not_serviced', 'no_driver_found', 'shop_no_show', 'provider_cancelled', 'provider_cancelled_in_transit', 'booking_failed']) {
        expect(deliveryProblemBody(en, reason), isNot(deliveryProblemBody(sw, reason)), reason: reason);
      }
      expect(deliveryProblemBody(en, 'area_not_serviced'), en.deliveryBoltNotServiced);
      expect(refundMessage(en, const DeliveryRefund(amount: 6400, status: 'pending')), contains('TSh 6,400'));
      expect(refundMessage(en, null), isNull);
    });

    test('return policies, reasons and statuses', () {
      for (final code in ['none', '24h', '3d', '7d']) {
        expect(returnPolicyLabel(en, code), isNot(returnPolicyLabel(sw, code)), reason: code);
      }
      for (final reason in ['changed_mind', 'wrong_item', 'damaged', 'not_as_described']) {
        expect(returnReasonLabel(en, reason), isNot(returnReasonLabel(sw, reason)), reason: reason);
      }
      for (final status in ['requested', 'accepted', 'rejected', 'disputed', 'return_in_transit', 'received', 'refunded', 'closed']) {
        expect(returnStatusLabel(en, status), isNot(returnStatusLabel(sw, status)), reason: status);
      }
    });

    test('payment failures, channels and the Bolt car line', () {
      expect(paymentFailureLabel(en, 'declined'), isNot(paymentFailureLabel(en, 'timeout')));
      expect(paymentFailureLabel(sw, 'timeout'), isNot(paymentFailureLabel(en, 'timeout')));
      expect(paymentChannelLabel(en, 'mixx'), 'Mixx by Yas');
      expect(boltVehicleSummary(const BoltDriver(model: 'Toyota IST', plate: 'T123ABC')), 'Toyota IST · T123ABC');
      expect(boltVehicleSummary(const BoltDriver(plate: 'T123ABC')), 'T123ABC');
    });
  });
}
