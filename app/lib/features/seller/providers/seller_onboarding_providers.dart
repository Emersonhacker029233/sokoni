import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../../core/providers.dart';
import '../../../data/models/seller_profile.dart';
import 'seller_providers.dart';

const _draftKey = 'seller_onboarding_draft';

class OnboardingDraft {
  const OnboardingDraft({this.sellerId, this.step = 0});

  /// Set once step 1 (business details) succeeds and a SellerProfile row
  /// exists server-side — every step from then on just PATCHes it, which
  /// is itself the "saved drafts" persistence CLAUDE.md calls for: even a
  /// killed app resumes exactly where the server record left off.
  final int? sellerId;
  final int step;

  OnboardingDraft copyWith({int? sellerId, int? step}) {
    return OnboardingDraft(sellerId: sellerId ?? this.sellerId, step: step ?? this.step);
  }

  Map<String, dynamic> toJson() => {'seller_id': sellerId, 'step': step};

  factory OnboardingDraft.fromJson(Map<String, dynamic> json) {
    return OnboardingDraft(sellerId: json['seller_id'] as int?, step: json['step'] as int? ?? 0);
  }
}

class SellerOnboardingController extends AsyncNotifier<OnboardingDraft> {
  @override
  Future<OnboardingDraft> build() async {
    final raw = await ref.watch(appDatabaseProvider).getKeyValue(_draftKey);
    if (raw == null) return const OnboardingDraft();
    return OnboardingDraft.fromJson(jsonDecode(raw) as Map<String, dynamic>);
  }

  Future<void> _persist(OnboardingDraft draft) async {
    await ref.read(appDatabaseProvider).setKeyValue(_draftKey, jsonEncode(draft.toJson()));
    state = AsyncData(draft);
  }

  Future<SellerProfile> submitBusiness({
    required String shopName,
    required String handle,
    required int categoryId,
    String? bio,
    String? whatsapp,
  }) async {
    try {
      final json = await ref.read(sellerApiProvider).submitBusiness({
        'shop_name': shopName,
        'handle': handle,
        'category_id': categoryId,
        'bio': bio,
        'whatsapp': whatsapp,
      });
      final seller = SellerProfile.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
      await _persist(OnboardingDraft(sellerId: seller.id, step: 1));
      return seller;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SellerProfile> submitLocation({
    required int sellerId,
    required double lat,
    required double lng,
    required String address,
    required String region,
    required String district,
  }) async {
    try {
      final json = await ref.read(sellerApiProvider).submitLocation(sellerId, {
        'lat': lat,
        'lng': lng,
        'address': address,
        'region': region,
        'district': district,
      });
      final seller = SellerProfile.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
      await _persist(OnboardingDraft(sellerId: sellerId, step: 2));
      return seller;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SellerProfile> submitIdentity({
    required int sellerId,
    required String nidaNumber,
    required String nidaImagePath,
  }) async {
    final seller = await ref
        .read(sellerRepositoryProvider)
        .submitIdentity(sellerId: sellerId, nidaNumber: nidaNumber, nidaImagePath: nidaImagePath);
    await _persist(OnboardingDraft(sellerId: sellerId, step: 3));
    return seller;
  }

  Future<SellerProfile> submitLicence({
    required int sellerId,
    String? licenceFilePath,
  }) async {
    final seller = await ref
        .read(sellerRepositoryProvider)
        .submitLicence(sellerId: sellerId, licenceFilePath: licenceFilePath);
    await _persist(OnboardingDraft(sellerId: sellerId, step: 4));
    return seller;
  }

  /// Seeds this wizard straight to its Identity step (index 2) for a
  /// seller who just registered through the "Create an account" flow
  /// (CLAUDE.md restructure, 2026-08-25) — that flow already collected
  /// and submitted the exact business+location fields steps 1-2 here
  /// would otherwise ask for again, so this resumes exactly where
  /// [submitLocation] would have left off, without duplicating the
  /// business/location screens or their submission logic.
  Future<void> resumeAtIdentity(int sellerId) async {
    await _persist(OnboardingDraft(sellerId: sellerId, step: 2));
  }

  Future<void> advanceToStep(int step) async {
    final current = state.value ?? const OnboardingDraft();
    await _persist(current.copyWith(step: step));
  }

  Future<void> complete() async {
    await ref.read(appDatabaseProvider).setKeyValue(_draftKey, jsonEncode(const OnboardingDraft().toJson()));
    state = const AsyncData(OnboardingDraft());
  }
}

final sellerOnboardingProvider = AsyncNotifierProvider<SellerOnboardingController, OnboardingDraft>(
  SellerOnboardingController.new,
);
