import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/validators.dart';
import '../providers/auth_providers.dart';
import 'post_sign_in.dart';

/// "Sign in" — for an existing account only: phone, then the code, done.
/// Never asks for a name (CLAUDE.md restructure, 2026-08-25 — the actual
/// reported bug: a returning user was shown a "full name" field as though
/// the account didn't exist, even though the server always found the
/// right row by phone; see DECISIONS.md for the diagnosis). If the number
/// turns out to be new, this offers "Create an account instead" rather
/// than continuing into a code field that could never succeed (the server
/// requires a name for a brand-new account, and this flow never collects
/// one).
Future<void> showSignInSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
    ),
    builder: (context) => const _SignInSheetContent(),
  );
}

enum _Step { phone, newNumber, code }

class _SignInSheetContent extends ConsumerStatefulWidget {
  const _SignInSheetContent();

  @override
  ConsumerState<_SignInSheetContent> createState() => _SignInSheetContentState();
}

class _SignInSheetContentState extends ConsumerState<_SignInSheetContent> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _codeController = TextEditingController();

  _Step _step = _Step.phone;
  String? _e164Phone;
  bool _isSubmitting = false;
  String? _errorText;

  @override
  void dispose() {
    _phoneController.dispose();
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _requestOtp() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final e164 = SokoniFormat.phoneToE164(_phoneController.text)!;

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      final isNewAccount = await ref.read(authRepositoryProvider).requestOtp(e164);
      if (!mounted) return;
      setState(() {
        _e164Phone = e164;
        _step = isNewAccount ? _Step.newNumber : _Step.code;
      });
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _verifyOtp() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSubmitting = true;
      _errorText = null;
    });
    try {
      final response = await ref.read(authRepositoryProvider).verifyOtp(
        phoneE164: _e164Phone!,
        code: _codeController.text.trim(),
      );
      if (mounted) await completeSignIn(context, ref, response);
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _tryAnotherNumber() {
    setState(() {
      _step = _Step.phone;
      _errorText = null;
      _codeController.clear();
    });
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
              _Step.phone => _phoneStep(l10n),
              _Step.newNumber => _newNumberStep(l10n),
              _Step.code => _codeStep(l10n),
            },
            if (_errorText != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_errorText!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            if (_step != _Step.newNumber)
              FilledButton(
                onPressed: _isSubmitting ? null : (_step == _Step.phone ? _requestOtp : _verifyOtp),
                child: _isSubmitting
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(_step == _Step.phone ? l10n.phoneSignInSendCode : l10n.phoneSignInVerify),
              ),
          ],
        ),
      ),
    );
  }

  Widget _phoneStep(AppLocalizations l10n) {
    return TextFormField(
      controller: _phoneController,
      keyboardType: TextInputType.phone,
      autofocus: true,
      decoration: InputDecoration(
        labelText: l10n.phoneSignInPhoneLabel,
        hintText: l10n.phoneSignInPhoneHint,
      ),
      validator: SokoniValidators.phone,
    );
  }

  Widget _newNumberStep(AppLocalizations l10n) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.signInNewNumberTitle, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: SokoniDimens.space4),
        Text(l10n.signInNewNumberBody, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: SokoniDimens.space20),
        FilledButton(
          onPressed: () {
            Navigator.of(context).pop();
            context.push(SokoniRoutes.createAccount);
          },
          child: Text(l10n.signInCreateAccountInstead),
        ),
        const SizedBox(height: SokoniDimens.space8),
        TextButton(onPressed: _tryAnotherNumber, child: Text(l10n.signInTryAnotherNumber)),
      ],
    );
  }

  Widget _codeStep(AppLocalizations l10n) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.signInWelcomeBackTitle, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: SokoniDimens.space4),
        Text(l10n.phoneSignInCodeSentTo(SokoniFormat.phoneMasked(_e164Phone!))),
        const SizedBox(height: SokoniDimens.space12),
        TextFormField(
          controller: _codeController,
          keyboardType: TextInputType.number,
          autofocus: true,
          maxLength: 6,
          decoration: InputDecoration(labelText: l10n.phoneSignInCodeLabel),
          validator: SokoniValidators.otpCode,
        ),
        TextButton(onPressed: _tryAnotherNumber, child: Text(l10n.signInTryAnotherNumber)),
      ],
    );
  }
}
