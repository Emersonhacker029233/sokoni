import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../legal_content.dart';

/// The Terms version the person agreed to on the sign-in sheet's Terms
/// step, this app session (Apple Guideline 1.2: "users must agree to terms
/// before registering or logging in"). Null until they tap Agree, so the
/// step shows first every time the app is opened fresh. AuthRepository
/// sends it with every sign-in request, and the server stores it on the
/// account in that same request.
class SignInTermsAgreement extends Notifier<String?> {
  @override
  String? build() => null;

  void agree() => state = sokoniTermsVersion;

  bool get agreedToCurrent => state == sokoniTermsVersion;
}

final signInTermsAgreementProvider = NotifierProvider<SignInTermsAgreement, String?>(SignInTermsAgreement.new);
