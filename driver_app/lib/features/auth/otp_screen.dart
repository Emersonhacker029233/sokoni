import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../shared/widgets/common.dart';

class OtpArgs {
  const OtpArgs({required this.phone, required this.isNewAccount});

  final String phone;
  final bool isNewAccount;
}

class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key, required this.args});

  final OtpArgs args;

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  static const _resendAfter = 60;

  final _form = GlobalKey<FormState>();
  final _code = TextEditingController();
  final _name = TextEditingController();
  bool _verifying = false;
  int _secondsLeft = _resendAfter;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  void _startCountdown() {
    _timer?.cancel();
    setState(() => _secondsLeft = _resendAfter);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_secondsLeft <= 1) t.cancel();
      if (mounted) setState(() => _secondsLeft--);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    _name.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _verifying = true);
    try {
      await ref.read(authRepositoryProvider).verifyOtp(
            phoneE164: widget.args.phone,
            code: _code.text.trim(),
            name: widget.args.isNewAccount ? _name.text : null,
          );
      ref.read(authProvider.notifier).signedIn();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _verifying = false);
    }
  }

  Future<void> _resend() async {
    final l = AppLocalizations.of(context);
    try {
      final locale = ref.read(localeProvider).value?.languageCode ?? 'sw';
      await ref.read(authRepositoryProvider).requestOtp(widget.args.phone, locale);
      _startCountdown();
      if (mounted) showMessage(context, l.codeSent);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(SokoniDimens.space24),
            children: [
              Text(l.otpTitle, style: theme.textTheme.displayLarge),
              const SizedBox(height: SokoniDimens.space12),
              Text(l.otpSentTo(DriverFormat.phoneLocal(widget.args.phone)), style: theme.textTheme.bodyLarge),
              const SizedBox(height: SokoniDimens.space32),
              TextFormField(
                controller: _code,
                autofocus: true,
                keyboardType: TextInputType.number,
                autofillHints: const [AutofillHints.oneTimeCode],
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                style: theme.textTheme.titleLarge?.copyWith(letterSpacing: 8),
                decoration: InputDecoration(labelText: l.codeLabel),
                validator: (v) => (v ?? '').trim().length == 6 ? null : l.codeInvalid,
              ),
              if (widget.args.isNewAccount) ...[
                const SizedBox(height: SokoniDimens.space20),
                Text(l.newAccountHint, style: theme.textTheme.bodyMedium),
                const SizedBox(height: SokoniDimens.space8),
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  autofillHints: const [AutofillHints.name],
                  decoration: InputDecoration(labelText: l.nameLabel),
                  validator: (v) => (v ?? '').trim().length < 3 ? l.nameRequired : null,
                ),
              ],
              const SizedBox(height: SokoniDimens.space24),
              PrimaryButton(label: l.verify, loading: _verifying, onPressed: _verify),
              const SizedBox(height: SokoniDimens.space8),
              TextButton(
                onPressed: _secondsLeft > 0 ? null : _resend,
                child: Text(_secondsLeft > 0 ? l.resendIn(_secondsLeft) : l.resendCode),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
