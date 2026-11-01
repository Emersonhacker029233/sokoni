import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/profile/profile_controller.dart';
import '../../shared/widgets/common.dart';
import '../l10n/gen/app_localizations.dart';
import '../providers.dart';

/// Logo while the session and profile load. If either can't be loaded it
/// says so and offers a retry instead of guessing "signed out".
class SplashScreen extends ConsumerWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authProvider);
    final profile = ref.watch(profileProvider);

    Widget body;
    if (auth == AuthStatus.unknown) {
      body = EmptyState(
        icon: Icons.lock_clock_outlined,
        title: l.sessionUnknown,
        actionLabel: l.retry,
        onAction: () => ref.read(authProvider.notifier).retry(),
      );
    } else if (auth == AuthStatus.signedIn && profile.hasError && !profile.hasValue) {
      body = Column(
        children: [
          Expanded(child: ErrorState(error: profile.error!, onRetry: () => ref.invalidate(profileProvider))),
          TextButton(
            onPressed: () async {
              await ref.read(authRepositoryProvider).logout();
              await ref.read(authProvider.notifier).signedOut();
            },
            child: Text(l.signOut),
          ),
          const SizedBox(height: 16),
        ],
      );
    } else {
      body = Center(
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0.85, end: 1),
          duration: const Duration(milliseconds: 600),
          curve: Curves.easeOutBack,
          builder: (context, v, child) => Opacity(opacity: v.clamp(0, 1), child: Transform.scale(scale: v, child: child)),
          child: Image.asset('assets/brand/sokoni_logo.png', width: 180, semanticLabel: l.appName),
        ),
      );
    }

    return Scaffold(body: SafeArea(child: body));
  }
}
