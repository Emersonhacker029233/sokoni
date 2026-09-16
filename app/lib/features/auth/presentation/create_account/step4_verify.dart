import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/utils/validators.dart';
import '../../../../shared/widgets/resend_code_button.dart';
import '../../presentation/post_sign_in.dart';
import '../../providers/create_account_providers.dart';

/// Step 4 — the last step, on purpose (CLAUDE.md restructure, 2026-08-25):
/// the code is sent to the number collected back at Step 2 and entered
/// here. On success the account (and, for a seller, the SellerProfile) is
/// created and the user is signed in — see
/// `CreateAccountController.submit()`.
///
/// The trade-off this ordering makes, logged as instructed: a seller can
/// fill in every field before Sokoni ever confirms the number is
/// reachable, and this is the exact point abandonment costs the most —
/// mitigated by Step 2 already validating the number's format and
/// checking it isn't registered, so this step is only ever a
/// confirmation, never a discovery of a problem that should have surfaced
/// earlier.
class CreateAccountStep4Verify extends ConsumerStatefulWidget {
  const CreateAccountStep4Verify({super.key});

  @override
  ConsumerState<CreateAccountStep4Verify> createState() => _CreateAccountStep4VerifyState();
}

class _CreateAccountStep4VerifyState extends ConsumerState<CreateAccountStep4Verify> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();
  bool _sending = false;
  bool _submitting = false;
  String? _error;
  bool _codeSent = false;
  DateTime? _codeExpiresAt;

  @override
  void initState() {
    super.initState();
    _sendCode();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _sendCode() async {
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final expiresAt = await ref.read(createAccountProvider.notifier).sendVerificationCode();
      if (mounted) {
        setState(() {
          _codeSent = true;
          _codeExpiresAt = expiresAt;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final response = await ref.read(createAccountProvider.notifier).submit(_codeController.text.trim());
      if (mounted) await completeSignIn(context, ref, response);
    } on ValidationException catch (e) {
      setState(() => _error = e.fieldError('code') ?? e.message);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final phone = ref.watch(createAccountProvider).value?.phone ?? '';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.createAccountVerifyTitle, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: SokoniDimens.space12),
            if (_codeSent)
              Text(l10n.createAccountVerifyBody(SokoniFormat.phoneLocal(phone)))
            else if (_sending)
              const LinearProgressIndicator(),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _codeController,
              keyboardType: TextInputType.number,
              autofocus: true,
              maxLength: 6,
              decoration: InputDecoration(labelText: l10n.phoneSignInCodeLabel),
              validator: SokoniValidators.otpCode,
            ),
            // Part 3 (client feedback): "Add a Resend code action... a
            // countdown... a tappable Resend code that requests a fresh
            // one without leaving the screen." Only shown once the
            // initial send has actually succeeded — resending before
            // that would just be _sendCode() racing itself.
            if (_codeExpiresAt != null)
              ResendCodeButton(
                codeExpiresAt: _codeExpiresAt!,
                onResend: () => ref.read(createAccountProvider.notifier).sendVerificationCode(),
              ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: SokoniDimens.space16),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.createAccountVerifyAction),
            ),
          ],
        ),
      ),
    );
  }
}
