import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/validators.dart';
import '../../auth/providers/auth_providers.dart';

/// Profile settings (C5) — name + optional email, with a verification
/// status/resend affordance once an email is set. Mirrors the website's
/// own `/account/settings` page so the same fields behave identically on
/// both surfaces.
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  bool _initialized = false;
  bool _saving = false;
  bool _resending = false;
  String? _error;
  String? _status;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
      _status = null;
    });
    try {
      await ref
          .read(authRepositoryProvider)
          .updateProfile(
            name: _nameController.text.trim(),
            email: _emailController.text.trim().isEmpty ? null : _emailController.text.trim(),
          );
      ref.invalidate(currentUserProvider);
      if (mounted) setState(() => _status = AppLocalizations.of(context).settingsSaved);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _resendVerification() async {
    setState(() {
      _resending = true;
      _error = null;
      _status = null;
    });
    try {
      await ref.read(authRepositoryProvider).resendVerificationEmail();
      if (mounted) setState(() => _status = AppLocalizations.of(context).settingsVerificationResent);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final userAsync = ref.watch(currentUserProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.settingsTitle)),
      body: userAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text('$error')),
        data: (user) {
          if (!_initialized) {
            _nameController.text = user.name;
            _emailController.text = user.email ?? '';
            _initialized = true;
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(SokoniDimens.space20),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextFormField(
                    controller: _nameController,
                    decoration: InputDecoration(labelText: l10n.settingsNameLabel),
                    validator: (v) => v == null || v.trim().isEmpty ? l10n.settingsNameLabel : null,
                  ),
                  const SizedBox(height: SokoniDimens.space16),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(labelText: l10n.settingsEmailLabel, hintText: l10n.settingsEmailHint),
                    validator: SokoniValidators.optionalEmail,
                  ),
                  if (user.email != null) ...[
                    const SizedBox(height: SokoniDimens.space8),
                    Row(
                      children: [
                        Icon(
                          user.emailVerified ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                          size: 16,
                          color: user.emailVerified ? SokoniColors.success : SokoniColors.sokoniBlack.withValues(alpha: 0.5),
                        ),
                        const SizedBox(width: SokoniDimens.space4),
                        Expanded(
                          child: Text(
                            user.emailVerified ? l10n.settingsEmailVerified : l10n.settingsEmailUnverified,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                      ],
                    ),
                    if (!user.emailVerified)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton(
                          onPressed: _resending ? null : _resendVerification,
                          child: Text(l10n.settingsResendVerification),
                        ),
                      ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: SokoniDimens.space8),
                    Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
                  ],
                  if (_status != null) ...[
                    const SizedBox(height: SokoniDimens.space8),
                    Text(_status!, style: const TextStyle(color: SokoniColors.success)),
                  ],
                  const SizedBox(height: SokoniDimens.space24),
                  FilledButton(
                    onPressed: _saving ? null : _save,
                    child: _saving
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : Text(l10n.settingsSave),
                  ),
                  const SizedBox(height: SokoniDimens.space24),
                  const Divider(),
                  const SizedBox(height: SokoniDimens.space8),
                  const _LanguageRow(),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Part 1 (language audit, client feedback): "Keep the existing language
/// switcher in settings... persist the choice across restarts and
/// across account switches." A minimal picker for now — Part 2 replaces
/// this with the full flag/dropdown/bottom-sheet component described
/// there; [LocaleController] (the actual default-resolution and
/// persistence logic) doesn't change when that happens.
class _LanguageRow extends ConsumerWidget {
  const _LanguageRow();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final saved = ref.watch(localeControllerProvider).value;
    final active = saved?.languageCode ?? resolveDeviceLocale(WidgetsBinding.instance.platformDispatcher.locales).languageCode;

    return Row(
      children: [
        Expanded(child: Text(l10n.settingsLanguageLabel, style: Theme.of(context).textTheme.titleSmall)),
        DropdownButton<String>(
          value: active,
          underline: const SizedBox.shrink(),
          items: [
            for (final code in supportedLanguageCodes)
              DropdownMenuItem(value: code, child: Text(languageEndonyms[code]!)),
          ],
          onChanged: (code) {
            if (code != null) ref.read(localeControllerProvider.notifier).setLocale(code);
          },
        ),
      ],
    );
  }
}
