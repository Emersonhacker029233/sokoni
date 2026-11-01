import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni_driver/core/providers.dart';
import 'package:sokoni_driver/core/router/app_router.dart';
import 'package:sokoni_driver/data/models/driver_profile.dart';
import 'package:sokoni_driver/features/profile/profile_controller.dart';

import 'fixtures.dart';

AsyncValue<ProfileState> profile(Map<String, dynamic>? json) =>
    AsyncData(ProfileState(json == null ? null : DriverProfile.fromJson(json)));

String? route(String location, {AuthStatus auth = AuthStatus.signedIn, AsyncValue<ProfileState>? p}) =>
    requiredRoute(auth: auth, profile: p ?? profile(null), location: location);

void main() {
  test('loading or an unreadable session stays on the splash', () {
    expect(route('/home', auth: AuthStatus.loading), '/splash');
    expect(route('/home', auth: AuthStatus.unknown), '/splash');
    expect(route('/home', p: const AsyncLoading()), '/splash');
  });

  test('signed out drivers only see the sign-in screens', () {
    expect(route('/home', auth: AuthStatus.signedOut), '/welcome');
    expect(route('/otp', auth: AuthStatus.signedOut), isNull);
    expect(route('/terms', auth: AuthStatus.signedOut), isNull);
  });

  test('no profile or an unsubmitted draft goes to onboarding', () {
    expect(route('/home'), '/onboarding');
    expect(route('/onboarding'), isNull);
    expect(route('/home', p: profile(profileJson())), '/onboarding');
  });

  test('settings and terms are reachable from anywhere once signed in', () {
    expect(route('/settings'), isNull);
    expect(route('/terms', p: profile(profileJson(submittedAt: '2026-10-31T10:00:00Z'))), isNull);
  });

  test('under review and suspended drivers wait on the status screen', () {
    expect(route('/home', p: profile(profileJson(submittedAt: '2026-10-31T10:00:00Z'))), '/status');
    expect(route('/home', p: profile(profileJson(status: 'suspended', submittedAt: '2026-10-31T10:00:00Z'))), '/status');
    expect(route('/onboarding', p: profile(profileJson(status: 'suspended', submittedAt: '2026-10-31T10:00:00Z'))), '/status');
  });

  test('rejected drivers may go back into onboarding to fix it', () {
    final rejected = profile(profileJson(status: 'rejected', submittedAt: '2026-10-31T10:00:00Z'));
    expect(route('/home', p: rejected), '/status');
    expect(route('/onboarding', p: rejected), isNull);
  });

  test('approved drivers land on home and move freely', () {
    final approved = profile(profileJson(status: 'approved', submittedAt: '2026-10-31T10:00:00Z', canGoOnline: true));
    expect(route('/splash', p: approved), '/home');
    expect(route('/onboarding', p: approved), '/home');
    expect(route('/earnings', p: approved), isNull);
    expect(route('/job', p: approved), isNull);
  });
}
