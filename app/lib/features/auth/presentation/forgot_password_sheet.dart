import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/validators.dart';
import '../providers/auth_providers.dart';
import 'post_sign_in.dart';

/// CLAUDE.md Part D 2.5 — username/phone, then an SMS code plus the new
/// password together. Step 1's response is deliberately identical whether
/// or not the account exists (`AuthRepository.forgotPasswordRequest`/
/// `PasswordAuthController`), so this screen never tells the user which —
/// it always moves on to step 2 after a successful request, rather than
/// branching on "found"/"not found".
Future<void> showForgotPasswordSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
    ),
    builder: (context) => const _ForgotPasswordSheetContent(),
  );
}

enum _Step { login, reset }

class _ForgotPasswordSheetContent extends ConsumerStatefulWidget {
  const _ForgotPasswordSheetContent();

  @override
  ConsumerState<_ForgotPasswordSheetContent> createState() => _ForgotPasswordSheetContentState();
}

class _ForgotPasswordSheetContentState extends ConsumerState<_ForgotPasswordSheetContent> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  _Step _step = _Step.login;
  bool _isSubmitting = false;
  String? _errorText;
  String? _infoText;

  @override
  void dispose() {
    _loginController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _requestCode() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      await ref.read(authRepositoryProvider).forgotPasswordRequest(_loginController.text.trim());
      if (!mounted) return;
      final l10n = AppLocalizations.of(context);
      setState(() {
        _step = _Step.reset;
        _infoText = l10n.forgotPasswordGenericSent;
      });
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _reset() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      final response = await ref
          .read(authRepositoryProvider)
          .forgotPasswordReset(
            login: _loginController.text.trim(),
            code: _codeController.text.trim(),
            password: _passwordController.text,
          );
      if (mounted) await completeSignIn(context, ref, response);
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
            Text(
              _step == _Step.login ? l10n.forgotPasswordTitle : l10n.forgotPasswordNewPasswordTitle,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: SokoniDimens.space8),
            switch (_step) {
              _Step.login => _loginStep(l10n),
              _Step.reset => _resetStep(l10n),
            },
            if (_infoText != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_infoText!, style: Theme.of(context).textTheme.bodySmall),
            ],
            if (_errorText != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_errorText!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: _isSubmitting ? null : (_step == _Step.login ? _requestCode : _reset),
              child: _isSubmitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(_step == _Step.login ? l10n.forgotPasswordSendCode : l10n.forgotPasswordResetAction),
            ),
          ],
        ),
      ),
    );
  }

  Widget _loginStep(AppLocalizations l10n) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.forgotPasswordBody, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: SokoniDimens.space12),
        TextFormField(
          controller: _loginController,
          autofocus: true,
          decoration: InputDecoration(labelText: l10n.loginFieldLabel),
          validator: (v) => (v == null || v.trim().isEmpty) ? l10n.loginFieldLabel : null,
        ),
      ],
    );
  }

  Widget _resetStep(AppLocalizations l10n) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextFormField(
          controller: _codeController,
          keyboardType: TextInputType.number,
          autofocus: true,
          maxLength: 6,
          decoration: InputDecoration(labelText: l10n.phoneSignInCodeLabel),
          validator: SokoniValidators.otpCode,
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
      ],
    );
  }
}
