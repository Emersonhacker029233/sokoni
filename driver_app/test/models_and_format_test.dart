import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni_driver/core/network/api_exception.dart';
import 'package:sokoni_driver/core/network/dio_client.dart';
import 'package:sokoni_driver/core/utils/formatters.dart';
import 'package:sokoni_driver/data/models/delivery_offer.dart';
import 'package:sokoni_driver/data/models/driver_job.dart';
import 'package:sokoni_driver/data/models/driver_profile.dart';
import 'package:sokoni_driver/data/models/earnings.dart';

import 'fixtures.dart';

DioException _response(int status, Map<String, dynamic> body) {
  final options = RequestOptions(path: '/driver/x');
  return DioException(
    requestOptions: options,
    type: DioExceptionType.badResponse,
    response: Response(requestOptions: options, statusCode: status, data: body),
  );
}

void main() {
  group('models parse the API resources', () {
    test('driver job', () {
      final job = DriverJob.fromJson(jobJson(status: 'arrived', next: ['delivered', 'failed'], collectCash: 4500));
      expect(job.orderCode, 'SKAB12••••');
      expect(job.itemsCount, 3);
      expect(job.shop.phone, '+255754000001');
      expect(job.timeline.last.status, 'driver_assigned');
      expect(job.collectCash, 4500);
      expect(job.isFinished, isFalse);
      // Round-trips through the offline cache.
      expect(DriverJob.fromJson(job.toJson()), job);
    });

    test('offer has no customer details', () {
      final offer = DeliveryOffer.fromJson(offerJson());
      expect(offer.driverEarnings, 3825);
      expect(offer.shop.district, 'Ilala');
      expect(offer.toJson().toString(), isNot(contains('customer')));
    });

    test('profile states', () {
      expect(DriverProfile.fromJson(profileJson()).isDraft, isTrue);
      final submitted = DriverProfile.fromJson(profileJson(submittedAt: '2026-10-31T10:00:00Z'));
      expect(submitted.isDraft, isFalse);
      expect(submitted.isUnderReview, isTrue);
      expect(DriverProfile.fromJson(profileJson(status: 'approved', submittedAt: '2026-10-31T10:00:00Z')).isApproved, isTrue);
      final withVehicle = DriverProfile.fromJson(profileJson(vehicle: {'type': 'bicycle', 'plate_number': null, 'verified': false}));
      expect(withVehicle.vehicle!.type, 'bicycle');
      expect(withVehicle.documents.licenceImage, isFalse);
    });

    test('earnings report', () {
      final report = EarningsReport.fromJson({
        'summary': {
          'from': '2026-10-26T00:00:00+03:00',
          'to': '2026-11-01T23:59:59+03:00',
          'jobs_count': 2,
          'delivered_count': 1,
          'gross_fees': 4500,
          'commission': 675,
          'earnings': 4825,
          'cash_collected': 0,
          'by_day': [
            {'date': '2026-11-01', 'jobs': 2, 'earnings': 4825},
          ],
          'jobs': [
            {'id': 1, 'order_code': 'SKAB12CD34', 'status': 'delivered', 'fee': 4500, 'commission': 675, 'earned_amount': 3825, 'payment_status': 'paid', 'distance_km': 4.2, 'settled_at': '2026-11-01T09:00:00+03:00', 'paid_out': false},
            {'id': 2, 'order_code': 'SKZZ12CD34', 'status': 'cancelled', 'fee': 3000, 'commission': 0, 'earned_amount': 1000, 'payment_status': 'paid', 'distance_km': 2, 'settled_at': '2026-11-01T10:00:00+03:00', 'paid_out': false},
          ],
        },
        'balance': {'unpaid_earnings': 4825, 'cash_held': 0, 'balance': 4825},
        'payouts': [
          {'id': 1, 'amount': 20000, 'status': 'paid', 'method': 'mpesa', 'reference': 'X1', 'period_start': '2026-10-19', 'period_end': '2026-10-25', 'paid_at': '2026-10-26T12:00:00Z'},
        ],
        'commission_pct': 15,
      });
      expect(report.summary.jobs.last.earnedAmount, 1000);
      expect(report.commissionPct, 15);
      expect(report.payouts.single.amount, 20000);
    });
  });

  group('mapDioError', () {
    test('delivery action refusals keep their code and extras', () {
      final e = mapDioError(_response(422, {'code': 'wrong_pin', 'message': 'PIN si sahihi', 'attempts_left': 3}));
      expect(e, isA<DeliveryActionException>());
      e as DeliveryActionException;
      expect(e.code, 'wrong_pin');
      expect(e.message, 'PIN si sahihi');
      expect(e.data['attempts_left'], 3);
    });

    test('409 already_taken', () {
      final e = mapDioError(_response(409, {'code': 'already_taken', 'message': 'Taken'})) as DeliveryActionException;
      expect(e.statusCode, 409);
    });

    test('Laravel validation errors surface the first field message', () {
      final e = mapDioError(_response(422, {
        'message': 'The given data was invalid.',
        'errors': {'plate_number': ['The plate number has already been taken.']},
      }));
      expect(e, isA<ValidationException>());
      expect(e.message, 'The plate number has already been taken.');
    });

    test('a bare 404 is NotFound (delivery switched off server-side)', () {
      expect(mapDioError(_response(404, {'message': 'Not Found'})), isA<NotFoundException>());
    });

    test('timeouts and dead connections count as connectivity errors', () {
      final options = RequestOptions(path: '/x');
      expect(isConnectivityError(mapDioError(DioException(requestOptions: options, type: DioExceptionType.connectionError))), isTrue);
      expect(isConnectivityError(mapDioError(DioException(requestOptions: options, type: DioExceptionType.receiveTimeout))), isTrue);
      expect(isConnectivityError(mapDioError(_response(500, {}))), isFalse);
    });
  });

  group('formatting', () {
    test('TZS has separators and no decimals', () {
      expect(DriverFormat.tzs(45000), 'TSh 45,000');
      expect(DriverFormat.tzs(1025000), 'TSh 1,025,000');
    });

    test('distances', () {
      expect(DriverFormat.km(0.42), '420 m');
      expect(DriverFormat.km(3.27), '3.3 km');
    });

    test('phones', () {
      expect(DriverFormat.phoneToE164('0754 123 456'), '+255754123456');
      expect(DriverFormat.phoneToE164('754123456'), '+255754123456');
      expect(DriverFormat.phoneToE164('255654123456'), '+255654123456');
      expect(DriverFormat.phoneToE164('0554123456'), isNull);
      expect(DriverFormat.phoneToE164('12345'), isNull);
      expect(DriverFormat.phoneLocal('+255754123456'), '0754 123 456');
    });
  });
}
