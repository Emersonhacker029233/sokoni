import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/validators.dart';
import '../providers/auth_providers.dart';

/// CLAUDE.md Part D 2.6 — shown once per sign-in to an account that
/// hasn't set a username/password yet (`SokoniUser.needsCredentialSetup`),
/// right after a successful old-flow (phone+code) sign-in. Skippable —
/// "do not lock anyone out" — so it reappears next time rather than ever
/// blocking the app; completing it the server returns
/// `needs_credential_setup: false` and it stops appearing.
Future<void> showCredentialSetupPrompt(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    isDismissible: true,
    useSafeArea: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
    ),
    builder: (context) => const _CredentialSetupPromptContent(),
  );
}

enum _CheckStatus { idle, checking, ok, problem, unknown }

const _checkTimeout = Duration(seconds: 6);

class _CredentialSetupPromptContent extends ConsumerStatefulWidget {
  const _CredentialSetupPromptContent();

  @override
  ConsumerState<_CredentialSetupPromptContent> createState() => _CredentialSetupPromptContentState();
}

class _CredentialSetupPromptContentState extends ConsumerState<_CredentialSetupPromptContent> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  Timer? _usernameDebounce;
  _CheckStatus _usernameStatus = _CheckStatus.idle;
  bool _isSubmitting = false;
  String? _errorText;

  @override
  void dispose() {
    _usernameDebounce?.cancel();
    _usernameController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  void _onUsernameChanged(String value) {
    _usernameDebounce?.cancel();
    if (SokoniValidators.username(value) != null) {
      setState(() => _usernameStatus = _CheckStatus.idle);
      return;
    }
    setState(() => _usernameStatus = _CheckStatus.checking);
    _usernameDebounce = Timer(const Duration(milliseconds: 500), () async {
      try {
        final available = await ref
            .read(authRepositoryProvider)
            .checkUsernameAvailable(value.trim())
            .timeout(_checkTimeout);
        if (mounted) setState(() => _usernameStatus = available ? _CheckStatus.ok : _CheckStatus.problem);
      } catch (_) {
        // Fail open, same reasoning as the handle-availability check on
        // the "Create an account" wizard — a broken live check is not
        // proof of anything wrong, and the real validation happens
        // server-side on submit regardless.
        if (mounted) setState(() => _usernameStatus = _CheckStatus.unknown);
      }
    });
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_usernameStatus == _CheckStatus.problem || _usernameStatus == _CheckStatus.checking) return;

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      await ref
          .read(authRepositoryProvider)
          .setCredentials(username: _usernameController.text.trim(), password: _passwordController.text);
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Padding(
      padding: EdgeInsets.only(
        left: SokoniDimens.space20,
        right: SokoniDimens.space20,
        top: SokoniDimens.space20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + SokoniDimens.space24,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.credentialSetupTitle, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space4),
            Text(l10n.credentialSetupBody, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _usernameController,
              autofocus: true,
              decoration: InputDecoration(
                labelText: l10n.usernameLabel,
                hintText: l10n.usernameHint,
                suffixIcon: switch (_usernameStatus) {
                  _CheckStatus.idle || _CheckStatus.unknown => null,
                  _CheckStatus.checking => const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                  ),
                  _CheckStatus.ok => const Icon(Icons.check_circle_rounded, color: SokoniColors.success),
                  _CheckStatus.problem => const Icon(Icons.error_rounded, color: SokoniColors.danger),
                },
              ),
              validator: SokoniValidators.username,
              onChanged: _onUsernameChanged,
            ),
            if (_usernameStatus == _CheckStatus.problem)
              Padding(
                padding: const EdgeInsets.only(top: SokoniDimens.space4),
                child: Text(l10n.usernameTaken, style: const TextStyle(color: SokoniColors.danger, fontSize: 12)),
              ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _passwordController,
              obscureText: true,
              decoration: InputDecoration(labelText: l10n.passwordLabel),
              validator: SokoniValidators.password,
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _confirmController,
              obscureText: true,
              decoration: InputDecoration(labelText: l10n.passwordConfirmLabel),
              validator: SokoniValidators.passwordConfirmation(_passwordController),
            ),
            if (_errorText != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_errorText!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: _isSubmitting ? null : _submit,
              child: _isSubmitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.credentialSetupAction),
            ),
            const SizedBox(height: SokoniDimens.space4),
            TextButton(
              onPressed: _isSubmitting ? null : () => Navigator.of(context).pop(),
              child: Text(l10n.credentialSetupSkip),
            ),
          ],
        ),
      ),
    );
  }
}
