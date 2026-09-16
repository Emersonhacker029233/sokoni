import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/colors.dart';

/// Part 3 (client feedback): "Add a Resend code action on the
/// verification screen" — shared by both the sign-in flow's code step
/// and the create-account flow's verify step, since the two need
/// identical behaviour: a countdown before resending becomes available,
/// clear feedback once a fresh code is sent, the code's real expiry
/// shown so a visitor understands why an old one stops working, and the
/// existing server-side rate limit (3 per phone per 15 minutes)
/// surfaced clearly rather than failing silently.
///
/// [onResend] is expected to call the same requestOtp the initial send
/// used and return the new code's expiry instant — the countdown resets
/// from that fresh value on success, exactly mirroring the real TTL
/// rather than restarting an independent client-side guess.
class ResendCodeButton extends StatefulWidget {
  const ResendCodeButton({required this.onResend, required this.codeExpiresAt, super.key, this.cooldownSeconds = 60});

  final Future<DateTime> Function() onResend;
  final DateTime codeExpiresAt;
  final int cooldownSeconds;

  @override
  State<ResendCodeButton> createState() => _ResendCodeButtonState();
}

class _ResendCodeButtonState extends State<ResendCodeButton> {
  Timer? _timer;
  late int _remaining = widget.cooldownSeconds;
  late DateTime _expiresAt = widget.codeExpiresAt;
  bool _sending = false;
  String? _feedback;
  bool _feedbackIsError = false;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startCountdown() {
    _timer?.cancel();
    _remaining = widget.cooldownSeconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() {
        if (_remaining > 0) _remaining--;
        if (_remaining == 0) timer.cancel();
      });
    });
  }

  Future<void> _handleResend() async {
    setState(() {
      _sending = true;
      _feedback = null;
    });
    try {
      final expiresAt = await widget.onResend();
      if (!mounted) return;
      setState(() {
        _expiresAt = expiresAt;
        _feedback = AppLocalizations.of(context).otpCodeResent;
        _feedbackIsError = false;
      });
      _startCountdown();
    } on RateLimitedException catch (e) {
      if (!mounted) return;
      setState(() {
        _feedback = e.message;
        _feedbackIsError = true;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _feedback = e.message;
        _feedbackIsError = true;
      });
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final expiresLabel = TimeOfDay.fromDateTime(_expiresAt.toLocal()).format(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l10n.otpCodeExpiresAt(expiresLabel),
          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: SokoniColors.sokoniBlack.withValues(alpha: 0.5)),
        ),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton(
            onPressed: (_remaining > 0 || _sending) ? null : _handleResend,
            child: Text(_remaining > 0 ? l10n.otpResendIn(_remaining) : l10n.createAccountResendCode),
          ),
        ),
        if (_feedback != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              _feedback!,
              style: TextStyle(color: _feedbackIsError ? SokoniColors.danger : SokoniColors.success, fontSize: 13),
            ),
          ),
      ],
    );
  }
}
