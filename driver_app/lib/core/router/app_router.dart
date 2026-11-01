import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/otp_screen.dart';
import '../../features/auth/password_screen.dart';
import '../../features/auth/welcome_screen.dart';
import '../../features/earnings/earnings_screen.dart';
import '../../features/history/history_screen.dart';
import '../../features/home/home_screen.dart';
import '../../features/job/job_screen.dart';
import '../../features/onboarding/onboarding_screen.dart';
import '../../features/onboarding/status_screen.dart';
import '../../features/profile/profile_controller.dart';
import '../../features/profile/profile_screen.dart';
import '../../features/profile/settings_screen.dart';
import '../providers.dart';
import 'app_shell.dart';
import 'splash_screen.dart';

const _authRoutes = {'/welcome', '/otp', '/password', '/terms'};
const _anytime = {'/settings', '/terms'};

/// Where a signed-in driver belongs, from their profile. Null means
/// "anywhere in the working app".
@visibleForTesting
String? requiredRoute({required AuthStatus auth, required AsyncValue<ProfileState> profile, required String location}) {
  if (auth == AuthStatus.loading || auth == AuthStatus.unknown) return '/splash';
  if (auth == AuthStatus.signedOut) return _authRoutes.contains(location) ? null : '/welcome';

  final value = profile.value;
  if (value == null) return '/splash';
  final p = value.profile;

  if (_anytime.contains(location)) return null;
  if (p == null || p.isDraft) return location == '/onboarding' ? null : '/onboarding';
  if (p.isRejected) return location == '/status' || location == '/onboarding' ? null : '/status';
  if (!p.isApproved) return location == '/status' ? null : '/status';

  const gated = {'/splash', '/onboarding', '/status', ..._authRoutes};
  return gated.contains(location) ? '/home' : null;
}

/// Bridges Riverpod state changes into go_router's refresh.
class _RouterRefresh extends ChangeNotifier {
  void ping() => notifyListeners();
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _RouterRefresh();
  ref
    ..listen(authProvider, (_, _) => refresh.ping())
    ..listen(profileProvider, (_, _) => refresh.ping())
    ..onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) {
      final target = requiredRoute(
        auth: ref.read(authProvider),
        profile: ref.read(profileProvider),
        location: state.matchedLocation,
      );
      return target == state.matchedLocation ? null : target;
    },
    routes: [
      GoRoute(path: '/splash', pageBuilder: (_, s) => _fade(s, const SplashScreen())),
      GoRoute(path: '/welcome', pageBuilder: (_, s) => _fade(s, const WelcomeScreen())),
      GoRoute(path: '/otp', pageBuilder: (_, s) => _slide(s, OtpScreen(args: s.extra! as OtpArgs))),
      GoRoute(path: '/password', pageBuilder: (_, s) => _slide(s, const PasswordScreen())),
      GoRoute(path: '/terms', pageBuilder: (_, s) => _slide(s, const TermsScreen())),
      GoRoute(path: '/settings', pageBuilder: (_, s) => _slide(s, const SettingsScreen())),
      GoRoute(path: '/onboarding', pageBuilder: (_, s) => _fade(s, const OnboardingScreen())),
      GoRoute(path: '/status', pageBuilder: (_, s) => _fade(s, const StatusScreen())),
      GoRoute(path: '/job', pageBuilder: (_, s) => _slide(s, const JobScreen(), vertical: true)),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/jobs', builder: (_, _) => const HistoryScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/earnings', builder: (_, _) => const EarningsScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/profile', builder: (_, _) => const ProfileScreen())]),
        ],
      ),
    ],
  );
});

const _duration = Duration(milliseconds: 280);

/// Fade-through: top-level state changes (splash, sign-in, onboarding).
CustomTransitionPage<void> _fade(GoRouterState state, Widget child) {
  return CustomTransitionPage(
    key: state.pageKey,
    child: child,
    transitionDuration: _duration,
    transitionsBuilder: (context, animation, secondary, child) {
      final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(scale: Tween(begin: 0.98, end: 1.0).animate(curved), child: child),
      );
    },
  );
}

/// Shared-axis drill-down: pushed screens; the job screen rises from below.
CustomTransitionPage<void> _slide(GoRouterState state, Widget child, {bool vertical = false}) {
  return CustomTransitionPage(
    key: state.pageKey,
    child: child,
    transitionDuration: _duration,
    reverseTransitionDuration: _duration,
    transitionsBuilder: (context, animation, secondary, child) {
      final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
      return FadeTransition(
        opacity: curved,
        child: SlideTransition(
          position: Tween(begin: vertical ? const Offset(0, 0.08) : const Offset(0.08, 0), end: Offset.zero).animate(curved),
          child: child,
        ),
      );
    },
  );
}
