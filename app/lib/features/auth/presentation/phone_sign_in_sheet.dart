import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/validators.dart';
import '../providers/auth_providers.dart';

/// Phone OTP sign-in as a bottom sheet (CLAUDE.md feature 4: "Google /
/// Facebook / Apple / phone OTP + full name, under 30 seconds"). Social
/// buttons join this sheet in Phase 6 alongside the seller wizard; phone
/// OTP is built first since it needs no external app credentials
/// (BLOCKERS.md item 3) and exercises the full auth stack end to end.
Future<void> showPhoneSignInSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
    ),
    builder: (context) => const _PhoneSignInSheetContent(),
  );
}

enum _Step { phone, otp }

class _PhoneSignInSheetContent extends ConsumerStatefulWidget {
  const _PhoneSignInSheetContent();

  @override
  ConsumerState<_PhoneSignInSheetContent> createState() => _PhoneSignInSheetContentState();
}

class _PhoneSignInSheetContentState extends ConsumerState<_PhoneSignInSheetContent> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _codeController = TextEditingController();
  final _nameController = TextEditingController();

  _Step _step = _Step.phone;
  String? _e164Phone;
  bool _isSubmitting = false;
  String? _errorText;

  @override
  void dispose() {
    _phoneController.dispose();
    _codeController.dispose();
    _nameController.dispose();
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
      await ref.read(authRepositoryProvider).requestOtp(e164);
      if (!mounted) return;
      setState(() {
        _e164Phone = e164;
        _step = _Step.otp;
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
      await ref.read(authRepositoryProvider).verifyOtp(
        phoneE164: _e164Phone!,
        code: _codeController.text.trim(),
        name: _nameController.text.trim().isEmpty ? null : _nameController.text.trim(),
      );
      ref.read(authStateProvider.notifier).markAuthenticated();
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
            Text(l10n.profileSignInAction, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space16),
            if (_step == _Step.phone) ..._phoneStep() else ..._otpStep(),
            if (_errorText != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_errorText!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: _isSubmitting
                  ? null
                  : (_step == _Step.phone ? _requestOtp : _verifyOtp),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(_step == _Step.phone ? 'Send code' : 'Verify'),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _phoneStep() {
    return [
      TextFormField(
        controller: _phoneController,
        keyboardType: TextInputType.phone,
        autofocus: true,
        decoration: const InputDecoration(labelText: 'Phone number', hintText: '0754 123 456'),
        validator: SokoniValidators.phone,
      ),
    ];
  }

  List<Widget> _otpStep() {
    return [
      Text('Code sent to ${SokoniFormat.phoneLocal(_e164Phone!)}'),
      const SizedBox(height: SokoniDimens.space12),
      TextFormField(
        controller: _codeController,
        keyboardType: TextInputType.number,
        autofocus: true,
        maxLength: 6,
        decoration: const InputDecoration(labelText: 'Verification code'),
        validator: SokoniValidators.otpCode,
      ),
      TextFormField(
        controller: _nameController,
        decoration: const InputDecoration(labelText: 'Full name (new accounts only)'),
      ),
    ];
  }
}
