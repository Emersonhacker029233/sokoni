import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/push/push_service.dart';
import 'package:sokoni/data/api/delivery_api.dart';
import 'package:sokoni/data/api/device_api.dart';
import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/data/models/user.dart';
import 'package:sokoni/data/repositories/delivery_repository.dart';
import 'package:sokoni/features/auth/providers/auth_providers.dart';
import 'package:sokoni/features/delivery/providers/delivery_providers.dart';

import '../../fixtures/delivery_fixtures.dart';

const testBuyer = SokoniUser(id: 1, name: 'Neema Buyer', phone: '+255711000111', isSeller: false, termsAccepted: true);
const testSeller = SokoniUser(id: 2, name: 'Juma Seller', phone: '+255722000222', isSeller: true, termsAccepted: true);

/// Answers the delivery calls from memory and records what was sent.
class FakeDeliveryRepository extends DeliveryRepository {
  FakeDeliveryRepository({this.view, this.afterPay}) : super(api: DeliveryApi(Dio()));

  Map<String, dynamic>? view;
  Map<String, dynamic>? afterPay;
  final List<Map<String, Object?>> calls = [];

  @override
  Future<DeliveryConfig?> config() async => DeliveryConfig.fromJson(deliveryConfigJson());

  @override
  Future<DeliveryTracking?> tracking(int orderId) async {
    calls.add({'call': 'tracking', 'order': orderId});
    return view == null ? null : DeliveryTracking.fromJson(view!);
  }

  @override
  Future<DeliveryTracking> pay(int orderId, {required String channel, required String msisdn, required String idempotencyKey}) async {
    calls.add({'call': 'pay', 'order': orderId, 'channel': channel, 'msisdn': msisdn, 'key': idempotencyKey});
    view = afterPay ?? view;
    return DeliveryTracking.fromJson(view!);
  }

  @override
  Future<DeliveryTracking> retry(int orderId) async {
    calls.add({'call': 'retry', 'order': orderId});
    return DeliveryTracking.fromJson(view!);
  }

  @override
  Future<void> rate(int orderId, {required int rating, String? comment}) async {
    calls.add({'call': 'rate', 'order': orderId, 'rating': rating, 'comment': comment});
  }

  @override
  Future<RealtimeCredentials?> realtimeToken() async => null;
}

/// Overrides shared by the delivery widget tests: no platform channels,
/// no network.
List<Override> deliveryOverrides({required FakeDeliveryRepository repo, SokoniUser user = testBuyer, bool deliveryOn = true}) => [
  deliveryRepositoryProvider.overrideWithValue(repo),
  deliveryAvailabilityProvider.overrideWith((ref) async => deliveryOn ? DeliveryConfig.fromJson(deliveryConfigJson()) : null),
  currentUserProvider.overrideWith((ref) async => user),
  pushServiceProvider.overrideWithValue(PushService(api: DeviceApi(Dio()))),
];

Widget localizedApp(Widget home, {Locale locale = const Locale('en')}) => MaterialApp(
  locale: locale,
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: AppLocalizations.supportedLocales,
  home: home,
);

/// Unmounts the tree so polling timers are cancelled before the test ends.
Future<void> disposeTree(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 30));
}
