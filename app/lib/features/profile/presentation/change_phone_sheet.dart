import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/motion/motion.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/validators.dart';
import '../../../shared/widgets/resend_code_button.dart';
import '../../auth/providers/auth_providers.dart';

/// Part 4 (client feedback): "phone number ... changing it needs
/// re-verification" — a two-step sheet (new number, then its OTP)
/// mirroring the sign-in flow's own phone/code steps, scoped to an
/// already-authenticated user instead of creating a session.
Future<void> showChangePhoneSheet(BuildContext context) {
  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.55,
    builder: (context) => const _ChangePhoneForm(),
  );
}

enum _Step { phone, code }

class _ChangePhoneForm extends ConsumerStatefulWidget {
  const _ChangePhoneForm();

  @override
  ConsumerState<_ChangePhoneForm> createState() => _ChangePhoneFormState();
}

class _ChangePhoneFormState extends ConsumerState<_ChangePhoneForm> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _codeController = TextEditingController();

  _Step _step = _Step.phone;
  String? _e164Phone;
  DateTime? _codeExpiresAt;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _phoneController.dispose();
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _requestCode() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final e164 = SokoniFormat.phoneToE164(_phoneController.text)!;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final expiresAt = await ref.read(authRepositoryProvider).requestPhoneChange(e164);
      if (!mounted) return;
      setState(() {
        _e164Phone = e164;
        _codeExpiresAt = expiresAt;
        _step = _Step.code;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _verifyCode() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref
          .read(authRepositoryProvider)
          .verifyPhoneChange(newPhoneE164: _e164Phone!, code: _codeController.text.trim());
      ref.invalidate(currentUserProvider);
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.changePhoneTitle, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: SokoniDimens.space8),
          Text(
            _step == _Step.phone ? l10n.changePhoneBody : l10n.changePhoneCodeBody(SokoniFormat.phoneLocal(_e164Phone!)),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: SokoniDimens.space20),
          if (_step == _Step.phone)
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              autofocus: true,
              decoration: InputDecoration(labelText: l10n.settingsPhoneLabel, prefixText: '+255 '),
              validator: SokoniValidators.phone,
            )
          else ...[
            TextFormField(
              controller: _codeController,
              keyboardType: TextInputType.number,
              autofocus: true,
              maxLength: 6,
              decoration: InputDecoration(labelText: l10n.phoneSignInCodeLabel),
              validator: SokoniValidators.otpCode,
            ),
            const SizedBox(height: SokoniDimens.space8),
            ResendCodeButton(
              codeExpiresAt: _codeExpiresAt!,
              onResend: () => ref.read(authRepositoryProvider).requestPhoneChange(_e164Phone!),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: SokoniDimens.space8),
            Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          ],
          const SizedBox(height: SokoniDimens.space20),
          FilledButton(
            onPressed: _submitting ? null : (_step == _Step.phone ? _requestCode : _verifyCode),
            child: _submitting
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : Text(_step == _Step.phone ? l10n.changePhoneSendCode : l10n.changePhoneConfirm),
          ),
        ],
      ),
    );
  }
}
