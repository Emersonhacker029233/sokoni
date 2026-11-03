import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/models/driver_profile.dart';
import '../../shared/launch.dart';
import '../../shared/widgets/common.dart';
import '../profile/profile_controller.dart';

/// Shown while a human reviews the documents, after a rejection, or when
/// the account is suspended. Pull down (or tap) to check again.
class StatusScreen extends ConsumerStatefulWidget {
  const StatusScreen({super.key});

  @override
  ConsumerState<StatusScreen> createState() => _StatusScreenState();
}

class _StatusScreenState extends ConsumerState<StatusScreen> {
  bool _checking = false;

  /// Without Firebase the decision arrives by SMS. Coming back to the app
  /// after reading it re-checks straight away, no tap needed.
  late final AppLifecycleListener _lifecycle = AppLifecycleListener(
    onResume: () {
      if (mounted && !_checking) _check();
    },
  );

  @override
  void initState() {
    super.initState();
    _lifecycle;
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _check() async {
    setState(() => _checking = true);
    await ref.read(profileProvider.notifier).refresh();
    if (mounted) setState(() => _checking = false);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final profile = ref.watch(profileProvider).value?.profile;
    if (profile == null) return const Scaffold(body: SizedBox.shrink());

    final (IconData icon, Color tint, String title, String body) = switch (profile) {
      DriverProfile(isSuspended: true) => (
          Icons.block_rounded,
          SokoniColors.danger,
          l.suspendedTitle,
          profile.suspendedReason ?? l.suspendedBody,
        ),
      DriverProfile(isRejected: true) => (
          Icons.edit_document,
          SokoniColors.danger,
          l.rejectedTitle,
          profile.rejectionReason == null ? '' : l.rejectedReason(profile.rejectionReason!),
        ),
      _ => (Icons.hourglass_top_rounded, SokoniColors.sokoniYellow, l.pendingTitle, l.pendingBody),
    };

    return Scaffold(
      appBar: AppBar(
        actions: [
          IconButton(tooltip: l.profileTitle, icon: const Icon(Icons.settings_outlined), onPressed: () => context.push('/settings')),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _check,
        child: ListView(
          padding: const EdgeInsets.all(SokoniDimens.space24),
          children: [
            const SizedBox(height: SokoniDimens.space32),
            Center(
              child: TweenAnimationBuilder<double>(
                tween: Tween(begin: 0.8, end: 1),
                duration: const Duration(milliseconds: 360),
                curve: Curves.easeOutBack,
                builder: (context, scale, child) => Transform.scale(scale: scale, child: child),
                child: Container(
                  width: 112,
                  height: 112,
                  decoration: BoxDecoration(color: tint.withValues(alpha: 0.15), shape: BoxShape.circle),
                  child: Icon(icon, size: 52, color: tint == SokoniColors.sokoniYellow ? theme.colorScheme.onSurface : tint),
                ),
              ),
            ),
            const SizedBox(height: SokoniDimens.space24),
            Text(title, style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: SokoniDimens.space12),
            if (body.isNotEmpty) Text(body, style: theme.textTheme.bodyLarge, textAlign: TextAlign.center),
            const SizedBox(height: SokoniDimens.space32),
            if (profile.isRejected)
              PrimaryButton(label: l.fixAndResubmit, onPressed: () => context.go('/onboarding'))
            else
              PrimaryButton(label: l.checkAgain, loading: _checking, onPressed: _check),
            const SizedBox(height: SokoniDimens.space12),
            OutlinedButton.icon(
              onPressed: openSupportEmail,
              icon: const Icon(Icons.support_agent_rounded),
              label: Text(l.contactSupport),
            ),
          ],
        ),
      ),
    );
  }
}
