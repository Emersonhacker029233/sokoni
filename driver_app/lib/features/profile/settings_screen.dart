import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../shared/launch.dart';
import '../../shared/widgets/common.dart';
import '../home/work_controller.dart';
import 'language_picker.dart';

/// Language, terms, support, sign out and account deletion. Reachable
/// from onboarding and the status screen too, so nobody is ever stuck.
class SettingsSection extends ConsumerWidget {
  const SettingsSection({super.key});

  Future<void> _signOut(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final work = ref.read(workProvider);
    if (work.online || work.job != null) {
      showMessage(context, l.signOutWhileOnline);
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        content: Text(l.signOutConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.cancel)),
          TextButton(onPressed: () => Navigator.pop(context, true), child: Text(l.signOut)),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(authRepositoryProvider).logout();
    await ref.read(authProvider.notifier).signedOut();
  }

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l.deleteAccount),
        content: Text(l.deleteAccountConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.cancel)),
          TextButton(
            style: TextButton.styleFrom(foregroundColor: SokoniColors.danger),
            onPressed: () => Navigator.pop(context, true),
            child: Text(l.deleteAccountAction),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(authRepositoryProvider).deleteAccount();
      await ref.read(authProvider.notifier).signedOut();
    } catch (e) {
      if (context.mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    return Column(
      children: [
        const LanguageTile(),
        ListTile(
          leading: const Icon(Icons.description_outlined),
          title: Text(l.terms),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/terms'),
        ),
        ListTile(
          leading: const Icon(Icons.support_agent_rounded),
          title: Text(l.contactSupport),
          subtitle: const Text('support@sokoni.co.tz'),
          onTap: openSupportEmail,
        ),
        const Divider(),
        ListTile(
          leading: const Icon(Icons.logout_rounded),
          title: Text(l.signOut),
          onTap: () => _signOut(context, ref),
        ),
        ListTile(
          leading: const Icon(Icons.delete_outline_rounded, color: SokoniColors.danger),
          title: Text(l.deleteAccount, style: const TextStyle(color: SokoniColors.danger)),
          onTap: () => _delete(context, ref),
        ),
      ],
    );
  }
}

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context).profileTitle)),
      body: const SafeArea(child: SingleChildScrollView(child: SettingsSection())),
    );
  }
}

class TermsScreen extends StatelessWidget {
  const TermsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.terms)),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Text(l.termsBody, style: Theme.of(context).textTheme.bodyLarge),
        ),
      ),
    );
  }
}
