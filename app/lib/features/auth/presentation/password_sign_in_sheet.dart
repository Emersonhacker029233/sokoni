import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/validators.dart';
import '../../../shared/widgets/resend_code_button.dart';
import '../providers/auth_providers.dart';
import 'forgot_password_sheet.dart';
import 'post_sign_in.dart';
import 'sign_in_sheet.dart';

/// "Sign in" (CLAUDE.md Part D 2.2) — username-or-phone + password is now
/// the primary path, replacing send-code-first. A code is only ever asked
/// for *after* a correct password, and only when this device hasn't
/// already completed that step for this exact login once (or two-factor
/// is explicitly on) — see `PasswordAuthController`/`AuthRepository.login`
/// server- and client-side. The old phone+code flow ([showSignInSheet])
/// stays one tap away for any account that hasn't set a password yet.
Future<void> showPasswordSignInSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
    ),
    builder: (context) => const _PasswordSignInSheetContent(),
  );
}

enum _Step { credentials, code }

class _PasswordSignInSheetContent extends ConsumerStatefulWidget {
  const _PasswordSignInSheetContent();

  @override
  ConsumerState<_PasswordSignInSheetContent> createState() => _PasswordSignInSheetContentState();
}

class _PasswordSignInSheetContentState extends ConsumerState<_PasswordSignInSheetContent> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();
  final _codeController = TextEditingController();

  _Step _step = _Step.credentials;
  String? _login;
  DateTime? _codeExpiresAt;
  bool _isSubmitting = false;
  String? _errorText;

  @override
  void dispose() {
    _loginController.dispose();
    _passwordController.dispose();
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _submitCredentials() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final login = _loginController.text.trim();

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      final challenge = await ref.read(authRepositoryProvider).login(login: login, password: _passwordController.text);
      if (!mounted) return;
      if (challenge.requiresCode) {
        setState(() {
          _login = login;
          _codeExpiresAt = challenge.codeExpiresAt;
          _step = _Step.code;
        });
      } else {
        await completeSignIn(context, ref, challenge.response!);
      }
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _verifyCode() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      final response = await ref.read(authRepositoryProvider).verifyLogin(login: _login!, code: _codeController.text.trim());
      if (mounted) await completeSignIn(context, ref, response);
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _useOldPhoneFlowInstead() {
    Navigator.of(context).pop();
    showSignInSheet(context);
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
            Text(l10n.authEntrySignInAction, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space16),
            switch (_step) {
              _Step.credentials => _credentialsStep(l10n),
              _Step.code => _codeStep(l10n),
            },
            if (_errorText != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_errorText!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: _isSubmitting ? null : (_step == _Step.credentials ? _submitCredentials : _verifyCode),
              child: _isSubmitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(_step == _Step.credentials ? l10n.loginAction : l10n.phoneSignInVerify),
            ),
          ],
        ),
      ),
    );
  }

  Widget _credentialsStep(AppLocalizations l10n) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextFormField(
          controller: _loginController,
          autofocus: true,
          decoration: InputDecoration(labelText: l10n.loginFieldLabel),
          validator: (v) => (v == null || v.trim().isEmpty) ? l10n.loginFieldLabel : null,
        ),
        const SizedBox(height: SokoniDimens.space16),
        TextFormField(
          controller: _passwordController,
          obscureText: true,
          decoration: InputDecoration(labelText: l10n.loginPasswordLabel),
          validator: (v) => (v == null || v.isEmpty) ? l10n.loginPasswordLabel : null,
        ),
        const SizedBox(height: SokoniDimens.space8),
        Align(
          alignment: Alignment.centerRight,
          child: TextButton(
            onPressed: () {
              Navigator.of(context).pop();
              showForgotPasswordSheet(context);
            },
            child: Text(l10n.loginForgotPassword),
          ),
        ),
        TextButton(onPressed: _useOldPhoneFlowInstead, child: Text(l10n.loginUsePhoneInstead)),
      ],
    );
  }

  Widget _codeStep(AppLocalizations l10n) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.loginCodeStepBody, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: SokoniDimens.space12),
        TextFormField(
          controller: _codeController,
          keyboardType: TextInputType.number,
          autofocus: true,
          maxLength: 6,
          decoration: InputDecoration(labelText: l10n.phoneSignInCodeLabel),
          validator: SokoniValidators.otpCode,
        ),
        if (_codeExpiresAt != null)
          ResendCodeButton(
            codeExpiresAt: _codeExpiresAt!,
            onResend: () async {
              final challenge = await ref
                  .read(authRepositoryProvider)
                  .login(login: _login!, password: _passwordController.text);
              return challenge.codeExpiresAt ?? DateTime.now();
            },
          ),
      ],
    );
  }
}
