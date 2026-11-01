import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/driver_job.dart';
import '../../shared/labels.dart';
import '../../shared/photo_picker.dart';
import '../../shared/widgets/common.dart';
import 'job_steps.dart';

/// Bottom sheet that asks for the seller's 4-character pickup code and
/// stays open on a wrong code so the driver can retry.
Future<void> showPickupCodeSheet(BuildContext context, {required Future<void> Function(String code) onSubmit}) {
  final l = AppLocalizations.of(context);
  return _codeSheet(
    context,
    title: l.pickupCodeTitle,
    body: l.pickupCodeBody,
    label: l.pickupCodeLabel,
    action: l.confirmPickup,
    length: 4,
    digitsOnly: false,
    invalid: l.pickupCodeInvalid,
    onSubmit: (code, _) => onSubmit(code),
  );
}

/// The customer's 4-digit PIN, plus the proof photo when the setting is on.
Future<void> showPinSheet(BuildContext context, {required DriverJob job, required Future<void> Function(String pin, String? photo) onSubmit}) {
  final l = AppLocalizations.of(context);
  return _codeSheet(
    context,
    title: l.pinTitle,
    body: l.pinBody,
    label: l.pinLabel,
    action: l.completeDelivery,
    length: 4,
    digitsOnly: true,
    invalid: l.pinInvalid,
    attemptsLeft: job.pinAttemptsLeft,
    needsPhoto: job.proofPhotoRequired,
    onSubmit: onSubmit,
  );
}

Future<void> _codeSheet(
  BuildContext context, {
  required String title,
  required String body,
  required String label,
  required String action,
  required int length,
  required bool digitsOnly,
  required String invalid,
  int? attemptsLeft,
  bool needsPhoto = false,
  required Future<void> Function(String code, String? photo) onSubmit,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => _CodeSheet(
      title: title,
      body: body,
      label: label,
      action: action,
      length: length,
      digitsOnly: digitsOnly,
      invalid: invalid,
      attemptsLeft: attemptsLeft,
      needsPhoto: needsPhoto,
      onSubmit: onSubmit,
    ),
  );
}

class _CodeSheet extends StatefulWidget {
  const _CodeSheet({
    required this.title,
    required this.body,
    required this.label,
    required this.action,
    required this.length,
    required this.digitsOnly,
    required this.invalid,
    required this.attemptsLeft,
    required this.needsPhoto,
    required this.onSubmit,
  });

  final String title;
  final String body;
  final String label;
  final String action;
  final int length;
  final bool digitsOnly;
  final String invalid;
  final int? attemptsLeft;
  final bool needsPhoto;
  final Future<void> Function(String code, String? photo) onSubmit;

  @override
  State<_CodeSheet> createState() => _CodeSheetState();
}

class _CodeSheetState extends State<_CodeSheet> {
  final _form = GlobalKey<FormState>();
  final _code = TextEditingController();
  String? _photo;
  String? _error;
  int? _attemptsLeft;
  bool _locked = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _attemptsLeft = widget.attemptsLeft;
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    if (!_form.currentState!.validate()) return;
    if (widget.needsPhoto && _photo == null) {
      setState(() => _error = l.proofPhotoRequired);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onSubmit(_code.text.trim().toUpperCase(), _photo);
      if (mounted) Navigator.of(context).pop();
    } on DeliveryActionException catch (e) {
      unawaited(HapticFeedback.mediumImpact());
      setState(() {
        _error = e.message;
        if (e.data['attempts_left'] is int) _attemptsLeft = e.data['attempts_left'] as int;
        if (e.code == 'pin_locked') _locked = true;
        if (e.code == 'proof_photo_required') _error = l.proofPhotoRequired;
      });
      _code.clear();
    } catch (e) {
      if (mounted) setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(SokoniDimens.space24, 0, SokoniDimens.space24, MediaQuery.viewInsetsOf(context).bottom + SokoniDimens.space24),
      child: Form(
        key: _form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(widget.title, style: theme.textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space8),
            Text(widget.body, style: theme.textTheme.bodyMedium),
            const SizedBox(height: SokoniDimens.space20),
            if (_locked)
              NoticeBanner(icon: Icons.lock_outline_rounded, text: l.pinLocked)
            else ...[
              TextFormField(
                controller: _code,
                autofocus: true,
                textAlign: TextAlign.center,
                keyboardType: widget.digitsOnly ? TextInputType.number : TextInputType.visiblePassword,
                textCapitalization: TextCapitalization.characters,
                inputFormatters: [
                  widget.digitsOnly ? FilteringTextInputFormatter.digitsOnly : FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9]')),
                  LengthLimitingTextInputFormatter(widget.length),
                ],
                style: theme.textTheme.displayLarge?.copyWith(letterSpacing: 16),
                decoration: InputDecoration(labelText: widget.label, errorText: _error),
                validator: (v) => (v ?? '').trim().length == widget.length ? null : widget.invalid,
                onFieldSubmitted: (_) => _submit(),
              ),
              if (_attemptsLeft != null && _attemptsLeft! < 5) ...[
                const SizedBox(height: 6),
                Text(l.pinAttemptsLeft(_attemptsLeft!), style: theme.textTheme.bodySmall?.copyWith(color: SokoniColors.danger)),
              ],
              if (widget.needsPhoto) ...[
                const SizedBox(height: SokoniDimens.space16),
                OutlinedButton.icon(
                  onPressed: () async {
                    final path = await pickPhoto(context, cameraOnly: true);
                    if (path != null) setState(() => _photo = path);
                  },
                  icon: _photo == null ? const Icon(Icons.photo_camera_outlined) : LocalThumb(path: _photo!, size: 28),
                  label: Text(_photo == null ? l.proofPhoto : l.proofPhotoTaken),
                ),
              ],
              const SizedBox(height: SokoniDimens.space20),
              PrimaryButton(label: widget.action, loading: _busy, onPressed: _submit),
            ],
          ],
        ),
      ),
    );
  }
}

/// Reasons a delivery can fail after pickup. "Customer unreachable" is
/// greyed out until the driver has waited and called enough.
Future<void> showFailSheet(BuildContext context, {required DriverJob job, required Future<void> Function(String reason, String? note) onSubmit}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => _FailSheet(job: job, onSubmit: onSubmit),
  );
}

class _FailSheet extends StatefulWidget {
  const _FailSheet({required this.job, required this.onSubmit});

  final DriverJob job;
  final Future<void> Function(String reason, String? note) onSubmit;

  @override
  State<_FailSheet> createState() => _FailSheetState();
}

class _FailSheetState extends State<_FailSheet> {
  static const _reasons = ['customer_unreachable', 'vehicle_breakdown', 'accident', 'item_damaged', 'wrong_address', 'other'];

  String? _reason;
  final _note = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_reason == null) return;
    setState(() => _busy = true);
    try {
      await widget.onSubmit(_reason!, _note.text.trim().isEmpty ? null : _note.text.trim());
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final job = widget.job;
    final unreachableOk = unreachableAllowed(job, DateTime.now());
    final locale = Localizations.localeOf(context).languageCode;

    return Padding(
      padding: EdgeInsets.fromLTRB(SokoniDimens.space24, 0, SokoniDimens.space24, MediaQuery.viewInsetsOf(context).bottom + SokoniDimens.space24),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.failTitle, style: theme.textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space12),
            RadioGroup<String>(
              groupValue: _reason,
              onChanged: (v) => setState(() => _reason = v),
              child: Column(
                children: [
                  for (final reason in _reasons)
                    RadioListTile<String>(
                      value: reason,
                      enabled: reason != 'customer_unreachable' || unreachableOk,
                      contentPadding: EdgeInsets.zero,
                      activeColor: SokoniColors.sokoniBlack,
                      title: Text(failureReasonLabel(l, reason)),
                      subtitle: reason == 'customer_unreachable' && !unreachableOk
                          ? Text(
                              [
                                if (job.unreachableAvailableAt != null) l.unreachableWait(DriverFormat.time(job.unreachableAvailableAt!, locale)),
                                l.callAttempts(job.callAttempts),
                              ].join(' '),
                            )
                          : null,
                    ),
                ],
              ),
            ),
            const SizedBox(height: SokoniDimens.space8),
            TextField(controller: _note, maxLines: 2, maxLength: 500, decoration: InputDecoration(labelText: l.failNote)),
            const SizedBox(height: SokoniDimens.space8),
            PrimaryButton(label: l.submit, loading: _busy, onPressed: _reason == null ? null : _submit),
          ],
        ),
      ),
    );
  }
}
