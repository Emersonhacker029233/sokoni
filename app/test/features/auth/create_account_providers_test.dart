import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/app_database.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/api/auth_api.dart';
import 'package:sokoni/data/api/seller_api.dart';
import 'package:sokoni/data/models/auth_response.dart';
import 'package:sokoni/data/models/seller_profile.dart';
import 'package:sokoni/data/models/user.dart';
import 'package:sokoni/data/repositories/auth_repository.dart';
import 'package:sokoni/data/repositories/seller_repository.dart';
import 'package:sokoni/features/auth/providers/auth_providers.dart';
import 'package:sokoni/features/auth/providers/create_account_providers.dart';
import 'package:sokoni/features/seller/providers/seller_onboarding_providers.dart';
import 'package:sokoni/features/seller/providers/seller_providers.dart';

/// Scripted [AuthRepository] — [register] returns a fixed [AuthResponse]
/// shape without any platform channel (the real one persists the token via
/// `SokoniSecureStorage`, unavailable in this plain test environment).
class _ScriptedAuthRepository extends AuthRepository {
  _ScriptedAuthRepository() : super(api: AuthApi(Dio()), storage: _NoopSecureStorage());

  final List<String> checkedPhones = [];
  final List<Map<String, dynamic>> registerCalls = [];

  @override
  Future<bool> checkPhoneExists(String phoneE164) async {
    checkedPhones.add(phoneE164);
    return phoneE164 == '+255700000001';
  }

  @override
  Future<({bool isNewAccount, DateTime expiresAt})> requestOtp(String phoneE164) async =>
      (isNewAccount: false, expiresAt: DateTime.now().add(const Duration(minutes: 5)));

  @override
  Future<AuthResponse> register({
    required String phoneE164,
    required String code,
    required String name,
    required String accountIntent,
    required String termsVersion,
    String? email,
    bool marketingConsent = false,
    String? shopName,
    String? handle,
    int? categoryId,
    String? region,
    String? district,
    String? address,
    String? whatsappE164,
  }) async {
    registerCalls.add({
      'phone': phoneE164,
      'code': code,
      'name': name,
      'email': email,
      'marketing_consent': marketingConsent,
      'account_intent': accountIntent,
      'shop_name': shopName,
      'handle': handle,
    });
    return AuthResponse(token: 'a-real-token', user: _fakeUser());
  }
}

class _NoopSecureStorage extends SokoniSecureStorage {
  @override
  Future<void> writeToken(String token) async {}
  @override
  Future<void> writeUserId(int id) async {}
}

SokoniUser _fakeUser() => SokoniUser.fromJson({
  'id': 1,
  'name': 'Test User',
  'is_seller': false,
  'terms_accepted': true,
});

class _ScriptedSellerRepository extends SellerRepository {
  _ScriptedSellerRepository() : super(api: SellerApi(Dio()), dio: Dio());

  final List<String> checkedHandles = [];

  @override
  Future<bool> checkHandleAvailable(String handle) async {
    checkedHandles.add(handle);
    return handle != 'taken_handle';
  }

  @override
  Future<SellerProfile> byHandle(String handle) async {
    return SellerProfile.fromJson({
      'id': 99,
      'shop_name': 'Test Shop',
      'handle': handle,
      'status': 'pending',
      'rating_avg': 0,
      'rating_count': 0,
    });
  }
}

Future<WidgetRef> _pumpAndCaptureRef(WidgetTester tester) async {
  late WidgetRef capturedRef;
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(AppDatabase.forTesting(NativeDatabase.memory())),
        authRepositoryProvider.overrideWithValue(_ScriptedAuthRepository()),
        sellerRepositoryProvider.overrideWithValue(_ScriptedSellerRepository()),
      ],
      child: Consumer(
        builder: (context, ref, _) {
          capturedRef = ref;
          return const SizedBox.shrink();
        },
      ),
    ),
  );
  return capturedRef;
}

void main() {
  testWidgets('choosing an intent persists it and advances to step 1', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);

    await ref.read(createAccountProvider.notifier).chooseIntent('sell');
    await tester.pump();

    final draft = ref.read(createAccountProvider).value!;
    expect(draft.intent, 'sell');
    expect(draft.isSeller, isTrue);
    expect(draft.step, 1);
  });

  testWidgets('the draft survives a fresh read from storage — the actual "never lose a part-filled form" requirement', (
    tester,
  ) async {
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    late WidgetRef ref;
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWithValue(db),
          authRepositoryProvider.overrideWithValue(_ScriptedAuthRepository()),
          sellerRepositoryProvider.overrideWithValue(_ScriptedSellerRepository()),
        ],
        child: Consumer(builder: (context, r, _) { ref = r; return const SizedBox.shrink(); }),
      ),
    );

    await ref.read(createAccountProvider.notifier).chooseIntent('buy');
    await ref.read(createAccountProvider.notifier).submitDetails(name: 'Amina', phone: '+255700000002');
    await tester.pump();

    // Directly inspect the underlying persisted row, simulating "the app
    // was killed and relaunched" without relying on the same provider
    // instance's in-memory state.
    final raw = await db.getKeyValue('create_account_draft');
    expect(raw, isNotNull);
    final saved = jsonDecode(raw!) as Map<String, dynamic>;
    expect(saved['name'], 'Amina');
    expect(saved['phone'], '+255700000002');
    expect(saved['intent'], 'buy');
  });

  testWidgets('checkPhoneExists and checkHandleAvailable delegate to the repositories', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);

    expect(await ref.read(createAccountProvider.notifier).checkPhoneExists('+255700000001'), isTrue);
    expect(await ref.read(createAccountProvider.notifier).checkPhoneExists('+255700000009'), isFalse);
    expect(await ref.read(createAccountProvider.notifier).checkHandleAvailable('taken_handle'), isFalse);
    expect(await ref.read(createAccountProvider.notifier).checkHandleAvailable('free_handle'), isTrue);
  });

  testWidgets('submitting a buyer registration sends no shop fields and clears the draft afterward', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);
    final repo = ref.read(authRepositoryProvider) as _ScriptedAuthRepository;

    await ref.read(createAccountProvider.notifier).chooseIntent('buy');
    await ref.read(createAccountProvider.notifier).submitDetails(name: 'Amina Buyer', phone: '+255700000002');
    await ref.read(createAccountProvider.notifier).acceptTerms('1.0');
    await ref.read(createAccountProvider.notifier).submit('123456');

    expect(repo.registerCalls, hasLength(1));
    expect(repo.registerCalls.single['account_intent'], 'buy');
    expect(repo.registerCalls.single['shop_name'], isNull);

    final draftAfter = ref.read(createAccountProvider).value!;
    expect(draftAfter.step, 0, reason: 'the draft is cleared back to a fresh one after a successful submit');
    expect(draftAfter.name, '');
  });

  testWidgets('an email typed at step 2 (C5, optional) is sent through to register()', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);
    final repo = ref.read(authRepositoryProvider) as _ScriptedAuthRepository;

    await ref.read(createAccountProvider.notifier).chooseIntent('buy');
    await ref
        .read(createAccountProvider.notifier)
        .submitDetails(name: 'Amina Buyer', phone: '+255700000002', email: 'amina@example.com');
    await ref.read(createAccountProvider.notifier).acceptTerms('1.0');
    await ref.read(createAccountProvider.notifier).submit('123456');

    expect(repo.registerCalls.single['email'], 'amina@example.com');
  });

  testWidgets('leaving the email blank at step 2 sends null, never an empty string', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);
    final repo = ref.read(authRepositoryProvider) as _ScriptedAuthRepository;

    await ref.read(createAccountProvider.notifier).chooseIntent('buy');
    await ref.read(createAccountProvider.notifier).submitDetails(name: 'Amina Buyer', phone: '+255700000002');
    await ref.read(createAccountProvider.notifier).acceptTerms('1.0');
    await ref.read(createAccountProvider.notifier).submit('123456');

    expect(repo.registerCalls.single['email'], isNull);
  });

  testWidgets('checking marketing consent at step 3 (C6) is sent through to register()', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);
    final repo = ref.read(authRepositoryProvider) as _ScriptedAuthRepository;

    await ref.read(createAccountProvider.notifier).chooseIntent('buy');
    await ref.read(createAccountProvider.notifier).submitDetails(name: 'Amina Buyer', phone: '+255700000002');
    await ref.read(createAccountProvider.notifier).acceptTerms('1.0');
    await ref.read(createAccountProvider.notifier).setMarketingConsent(true);
    await ref.read(createAccountProvider.notifier).submit('123456');

    expect(repo.registerCalls.single['marketing_consent'], isTrue);
  });

  testWidgets('leaving marketing consent unchecked sends false, not null', (tester) async {
    final ref = await _pumpAndCaptureRef(tester);
    final repo = ref.read(authRepositoryProvider) as _ScriptedAuthRepository;

    await ref.read(createAccountProvider.notifier).chooseIntent('buy');
    await ref.read(createAccountProvider.notifier).submitDetails(name: 'Amina Buyer', phone: '+255700000002');
    await ref.read(createAccountProvider.notifier).acceptTerms('1.0');
    await ref.read(createAccountProvider.notifier).submit('123456');

    expect(repo.registerCalls.single['marketing_consent'], isFalse);
  });

  testWidgets(
    'submitting a seller registration sends the shop fields and seeds the seller onboarding '
    'wizard straight to its Identity step — the actual "verify your shop" reuse this flow depends on',
    (tester) async {
      final ref = await _pumpAndCaptureRef(tester);

      await ref.read(createAccountProvider.notifier).chooseIntent('sell');
      await ref
          .read(createAccountProvider.notifier)
          .submitDetails(
            name: 'Baraka Seller',
            phone: '+255700000003',
            shopName: 'Baraka Electronics',
            handle: 'baraka_electronics',
            categoryId: 1,
            region: 'Dar es Salaam',
            district: 'Kinondoni',
            address: 'Mwenge Road',
          );
      await ref.read(createAccountProvider.notifier).acceptTerms('1.0');
      await ref.read(createAccountProvider.notifier).submit('123456');

      final repo = ref.read(authRepositoryProvider) as _ScriptedAuthRepository;
      expect(repo.registerCalls.single['account_intent'], 'sell');
      expect(repo.registerCalls.single['shop_name'], 'Baraka Electronics');
      expect(repo.registerCalls.single['handle'], 'baraka_electronics');

      final onboardingDraft = ref.read(sellerOnboardingProvider).value!;
      expect(onboardingDraft.sellerId, 99);
      expect(onboardingDraft.step, 2, reason: 'index 2 is the Identity step — business+location are already done');
    },
  );
}
