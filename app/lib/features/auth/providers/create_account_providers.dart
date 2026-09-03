import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../data/models/auth_response.dart';
import '../../seller/providers/seller_onboarding_providers.dart';
import '../../seller/providers/seller_providers.dart';
import 'auth_providers.dart';

const _createAccountDraftKey = 'create_account_draft';

/// Steps of the "Create an account" flow (CLAUDE.md restructure,
/// 2026-08-25): 0 = intent (buy/sell), 1 = details, 2 = terms, 3 = verify.
class CreateAccountDraft {
  const CreateAccountDraft({
    this.step = 0,
    this.intent,
    this.name = '',
    this.phone = '',
    this.email = '',
    this.shopName = '',
    this.handle = '',
    this.categoryId,
    this.region = '',
    this.district = '',
    this.address = '',
    this.whatsapp = '',
    this.termsAccepted = false,
    this.termsVersion,
    this.marketingConsent = false,
  });

  final int step;
  final String? intent;
  final String name;

  /// E.164 — collected once at Step 2, never re-asked at Step 4 (which
  /// only sends/verifies the code to this same number).
  final String phone;

  /// Optional (C5) — an empty string means "not provided", same convention
  /// as [whatsapp] below, not a sentinel worth its own nullable field.
  final String email;

  final String shopName;
  final String handle;
  final int? categoryId;
  final String region;
  final String district;
  final String address;
  final String whatsapp;
  final bool termsAccepted;
  final String? termsVersion;

  /// C6 — opt-in, shown alongside the Terms checkbox at Step 3.
  final bool marketingConsent;

  bool get isSeller => intent == 'sell';

  CreateAccountDraft copyWith({
    int? step,
    String? intent,
    String? name,
    String? phone,
    String? email,
    String? shopName,
    String? handle,
    int? categoryId,
    String? region,
    String? district,
    String? address,
    String? whatsapp,
    bool? termsAccepted,
    String? termsVersion,
    bool? marketingConsent,
  }) {
    return CreateAccountDraft(
      step: step ?? this.step,
      intent: intent ?? this.intent,
      name: name ?? this.name,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      shopName: shopName ?? this.shopName,
      handle: handle ?? this.handle,
      categoryId: categoryId ?? this.categoryId,
      region: region ?? this.region,
      district: district ?? this.district,
      address: address ?? this.address,
      whatsapp: whatsapp ?? this.whatsapp,
      termsAccepted: termsAccepted ?? this.termsAccepted,
      termsVersion: termsVersion ?? this.termsVersion,
      marketingConsent: marketingConsent ?? this.marketingConsent,
    );
  }

  Map<String, dynamic> toJson() => {
    'step': step,
    'intent': intent,
    'name': name,
    'phone': phone,
    'email': email,
    'shop_name': shopName,
    'handle': handle,
    'category_id': categoryId,
    'region': region,
    'district': district,
    'address': address,
    'whatsapp': whatsapp,
    'terms_accepted': termsAccepted,
    'terms_version': termsVersion,
    'marketing_consent': marketingConsent,
  };

  factory CreateAccountDraft.fromJson(Map<String, dynamic> json) {
    return CreateAccountDraft(
      step: json['step'] as int? ?? 0,
      intent: json['intent'] as String?,
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      email: json['email'] as String? ?? '',
      shopName: json['shop_name'] as String? ?? '',
      handle: json['handle'] as String? ?? '',
      categoryId: json['category_id'] as int?,
      region: json['region'] as String? ?? '',
      district: json['district'] as String? ?? '',
      address: json['address'] as String? ?? '',
      whatsapp: json['whatsapp'] as String? ?? '',
      termsAccepted: json['terms_accepted'] as bool? ?? false,
      termsVersion: json['terms_version'] as String?,
      marketingConsent: json['marketing_consent'] as bool? ?? false,
    );
  }
}

/// Drives the whole "Create an account" flow. Unlike
/// [SellerOnboardingController] (whose draft is just a `{sellerId, step}`
/// pointer, because every step there immediately PATCHes a real server
/// row), no account exists yet here until Step 4 succeeds — so the full
/// form contents themselves have to be the local draft, or a dropped
/// connection or an interrupting phone call between steps would wipe
/// everything the person just typed (CLAUDE.md restructure, 2026-08-25:
/// "this matters on 3G"). Persisted the same way the rest of this app
/// persists small local records — the drift-backed key-value table,
/// not a new local-storage package.
class CreateAccountController extends AsyncNotifier<CreateAccountDraft> {
  @override
  Future<CreateAccountDraft> build() async {
    final raw = await ref.watch(appDatabaseProvider).getKeyValue(_createAccountDraftKey);
    if (raw == null) return const CreateAccountDraft();
    return CreateAccountDraft.fromJson(jsonDecode(raw) as Map<String, dynamic>);
  }

  Future<void> _persist(CreateAccountDraft draft) async {
    await ref.read(appDatabaseProvider).setKeyValue(_createAccountDraftKey, jsonEncode(draft.toJson()));
    state = AsyncData(draft);
  }

  Future<void> chooseIntent(String intent) async {
    final current = state.value ?? const CreateAccountDraft();
    await _persist(current.copyWith(intent: intent, step: 1));
  }

  /// Called once Step 2's fields all pass their local + live checks —
  /// saves the details and advances. Buyer details omit every shop field.
  Future<void> submitDetails({
    required String name,
    required String phone,
    String? email,
    String? shopName,
    String? handle,
    int? categoryId,
    String? region,
    String? district,
    String? address,
    String? whatsapp,
  }) async {
    final current = state.value ?? const CreateAccountDraft();
    await _persist(
      current.copyWith(
        name: name,
        phone: phone,
        email: email ?? '',
        shopName: shopName ?? '',
        handle: handle ?? '',
        categoryId: categoryId,
        region: region ?? '',
        district: district ?? '',
        address: address ?? '',
        whatsapp: whatsapp ?? '',
        step: 2,
      ),
    );
  }

  Future<void> acceptTerms(String version) async {
    final current = state.value ?? const CreateAccountDraft();
    await _persist(current.copyWith(termsAccepted: true, termsVersion: version, step: 3));
  }

  /// C6 — the opt-in marketing checkbox shown alongside Terms; not gated
  /// behind the Continue button since it carries no validation of its own.
  Future<void> setMarketingConsent(bool value) async {
    final current = state.value ?? const CreateAccountDraft();
    await _persist(current.copyWith(marketingConsent: value));
  }

  /// Step 2 live validation — no OTP side effect (see AuthRepository docs).
  Future<bool> checkPhoneExists(String phoneE164) {
    return ref.read(authRepositoryProvider).checkPhoneExists(phoneE164);
  }

  /// Step 2 live validation, seller path only.
  Future<bool> checkHandleAvailable(String handle) {
    return ref.read(sellerRepositoryProvider).checkHandleAvailable(handle);
  }

  /// Step 4 — sends the code to the phone number collected back at Step 2.
  Future<void> sendVerificationCode() async {
    final phone = (state.value ?? const CreateAccountDraft()).phone;
    await ref.read(authRepositoryProvider).requestOtp(phone);
  }

  /// Step 4 on success — verifies the code and creates the account (and,
  /// for a seller, the SellerProfile) in one request, then clears the
  /// local draft. For a new seller, also seeds the *existing* seller
  /// onboarding wizard's own local draft straight to its Identity step
  /// (step index 2) — NIDA/licence upload deliberately stays that
  /// separate "verify your shop" flow, entered here rather than
  /// duplicated, per instruction: identity documents don't belong on an
  /// account-creation form.
  Future<AuthResponse> submit(String code) async {
    final d = state.value ?? const CreateAccountDraft();

    final response = await ref
        .read(authRepositoryProvider)
        .register(
          phoneE164: d.phone,
          code: code,
          name: d.name,
          email: d.email.isNotEmpty ? d.email : null,
          marketingConsent: d.marketingConsent,
          accountIntent: d.intent!,
          termsVersion: d.termsVersion!,
          shopName: d.isSeller ? d.shopName : null,
          handle: d.isSeller ? d.handle : null,
          categoryId: d.isSeller ? d.categoryId : null,
          region: d.isSeller ? d.region : null,
          district: d.isSeller ? d.district : null,
          address: d.isSeller ? d.address : null,
          whatsappE164: d.isSeller && d.whatsapp.isNotEmpty ? d.whatsapp : null,
        );

    if (d.isSeller) {
      final seller = await ref.read(sellerRepositoryProvider).byHandle(d.handle);
      await ref.read(sellerOnboardingProvider.notifier).resumeAtIdentity(seller.id);
    }

    await clear();
    return response;
  }

  Future<void> goToStep(int step) async {
    final current = state.value ?? const CreateAccountDraft();
    await _persist(current.copyWith(step: step));
  }

  Future<void> clear() async {
    await ref.read(appDatabaseProvider).setKeyValue(_createAccountDraftKey, jsonEncode(const CreateAccountDraft().toJson()));
    state = const AsyncData(CreateAccountDraft());
  }
}

final createAccountProvider = AsyncNotifierProvider<CreateAccountController, CreateAccountDraft>(
  CreateAccountController.new,
);
