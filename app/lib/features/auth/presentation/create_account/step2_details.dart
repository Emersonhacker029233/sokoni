import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/utils/validators.dart';
import '../../../../data/models/category.dart';
import '../../../discovery/providers/discovery_providers.dart';
import '../../providers/create_account_providers.dart';

/// [unknown] is the fail-open outcome — the check errored or timed out.
/// It must never behave like [problem]: a broken or slow live-availability
/// check is not proof of anything wrong, and the second time this app
/// trapped a user behind a dead Next button was one too many (see
/// DECISIONS.md). [RegisterAccountRequest] re-validates both the phone
/// and the handle for real at Step 4 regardless of what this screen
/// managed to confirm, so failing open here costs nothing but a slightly
/// later error in the rare case the live check was actually right.
enum _CheckStatus { idle, checking, ok, problem, unknown }

/// How long a live check is allowed to hang before this screen gives up
/// and fails open — short enough that a broken/unreachable endpoint
/// (confirmed to happen — see DECISIONS.md) resolves quickly rather than
/// leaving the Next button looking dead with no explanation.
const _checkTimeout = Duration(seconds: 6);

/// Step 2 — buyer: full name + phone, that's all. Seller: the same, plus
/// shop name/handle/category/region/district/address/WhatsApp — the NIDA
/// number is deliberately not asked for here (CLAUDE.md restructure,
/// 2026-08-25: identity verification belongs in a separate "verify your
/// shop" step *after* the account exists, reusing the existing onboarding
/// wizard's Identity step — see `CreateAccountController.submit()`).
///
/// Validates as it goes, not at the end: the phone number and (for a
/// seller) the handle are checked against the server the moment their
/// format is locally valid, debounced, so "this number already has an
/// account" or "this handle is taken" surfaces here — while there's still
/// something easy to do about it — rather than at the final verify step
/// after everything else has been filled in.
class CreateAccountStep2Details extends ConsumerStatefulWidget {
  const CreateAccountStep2Details({required this.onSubmitted, super.key});

  final VoidCallback onSubmitted;

  @override
  ConsumerState<CreateAccountStep2Details> createState() => _CreateAccountStep2DetailsState();
}

class _CreateAccountStep2DetailsState extends ConsumerState<CreateAccountStep2Details> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _shopNameController = TextEditingController();
  final _handleController = TextEditingController();
  final _regionController = TextEditingController();
  final _districtController = TextEditingController();
  final _addressController = TextEditingController();
  final _whatsappController = TextEditingController();
  int? _categoryId;

  Timer? _phoneDebounce;
  Timer? _handleDebounce;
  _CheckStatus _phoneStatus = _CheckStatus.idle;
  _CheckStatus _handleStatus = _CheckStatus.idle;
  bool _submitting = false;

  @override
  void dispose() {
    _phoneDebounce?.cancel();
    _handleDebounce?.cancel();
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _shopNameController.dispose();
    _handleController.dispose();
    _regionController.dispose();
    _districtController.dispose();
    _addressController.dispose();
    _whatsappController.dispose();
    super.dispose();
  }

  void _onPhoneChanged(String value) {
    _phoneDebounce?.cancel();
    final e164 = SokoniFormat.phoneToE164(value);
    if (e164 == null) {
      setState(() => _phoneStatus = _CheckStatus.idle);
      return;
    }
    setState(() => _phoneStatus = _CheckStatus.checking);
    _phoneDebounce = Timer(const Duration(milliseconds: 500), () async {
      try {
        final exists = await ref.read(createAccountProvider.notifier).checkPhoneExists(e164).timeout(_checkTimeout);
        if (mounted) setState(() => _phoneStatus = exists ? _CheckStatus.problem : _CheckStatus.ok);
      } catch (_) {
        // Fail open — see _CheckStatus.unknown's docs.
        if (mounted) setState(() => _phoneStatus = _CheckStatus.unknown);
      }
    });
  }

  void _onHandleChanged(String value) {
    _handleDebounce?.cancel();
    if (SokoniValidators.handle(value) != null) {
      setState(() => _handleStatus = _CheckStatus.idle);
      return;
    }
    setState(() => _handleStatus = _CheckStatus.checking);
    _handleDebounce = Timer(const Duration(milliseconds: 500), () async {
      try {
        final available = await ref
            .read(createAccountProvider.notifier)
            .checkHandleAvailable(value.trim())
            .timeout(_checkTimeout);
        if (mounted) setState(() => _handleStatus = available ? _CheckStatus.ok : _CheckStatus.problem);
      } catch (_) {
        // Fail open — see _CheckStatus.unknown's docs.
        if (mounted) setState(() => _handleStatus = _CheckStatus.unknown);
      }
    });
  }

  Future<void> _submit(bool isSeller) async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    // A live check still briefly holds up Next while genuinely in flight
    // — that's normal, bounded UX, not the bug. What must never happen is
    // "checking" persisting forever: _onPhoneChanged/_onHandleChanged
    // guarantee it always resolves to ok/problem/unknown within
    // _checkTimeout, so this can only ever block for a few seconds at
    // worst, never indefinitely. A failed/timed-out check resolves to
    // _CheckStatus.unknown, which — unlike problem — does NOT block here.
    if (_phoneStatus == _CheckStatus.problem || _phoneStatus == _CheckStatus.checking) return;
    if (isSeller && (_handleStatus == _CheckStatus.problem || _handleStatus == _CheckStatus.checking)) return;
    if (isSeller && _categoryId == null) return;

    setState(() => _submitting = true);
    final e164Phone = SokoniFormat.phoneToE164(_phoneController.text)!;
    final e164Whatsapp = _whatsappController.text.trim().isEmpty
        ? null
        : SokoniFormat.phoneToE164(_whatsappController.text.trim());

    await ref
        .read(createAccountProvider.notifier)
        .submitDetails(
          name: _nameController.text.trim(),
          phone: e164Phone,
          email: _emailController.text.trim().isEmpty ? null : _emailController.text.trim(),
          shopName: isSeller ? _shopNameController.text.trim() : null,
          handle: isSeller ? _handleController.text.trim() : null,
          categoryId: isSeller ? _categoryId : null,
          region: isSeller ? _regionController.text.trim() : null,
          district: isSeller ? _districtController.text.trim() : null,
          address: isSeller ? _addressController.text.trim() : null,
          whatsapp: isSeller ? e164Whatsapp : null,
        );
    if (mounted) setState(() => _submitting = false);
    widget.onSubmitted();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final draft = ref.watch(createAccountProvider).value;
    final isSeller = draft?.isSeller ?? false;
    final locale = Localizations.localeOf(context).languageCode;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.createAccountDetailsTitle, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: SokoniDimens.space20),
            TextFormField(
              controller: _nameController,
              decoration: InputDecoration(labelText: l10n.createAccountFullName),
              validator: (v) => SokoniValidators.required(v),
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: l10n.phoneSignInPhoneLabel,
                hintText: l10n.phoneSignInPhoneHint,
                suffixIcon: _CheckIcon(status: _phoneStatus),
              ),
              validator: SokoniValidators.phone,
              onChanged: _onPhoneChanged,
            ),
            if (_phoneStatus == _CheckStatus.problem)
              Padding(
                padding: const EdgeInsets.only(top: SokoniDimens.space4),
                child: Text(
                  l10n.createAccountPhoneAlreadyRegistered,
                  style: const TextStyle(color: SokoniColors.danger, fontSize: 12),
                ),
              ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: InputDecoration(labelText: l10n.settingsEmailLabel, hintText: l10n.settingsEmailHint),
              validator: SokoniValidators.optionalEmail,
            ),
            if (isSeller) ...[
              const SizedBox(height: SokoniDimens.space24),
              Divider(color: Theme.of(context).dividerColor),
              const SizedBox(height: SokoniDimens.space8),
              TextFormField(
                controller: _shopNameController,
                decoration: InputDecoration(labelText: l10n.onboardingShopName),
                validator: (v) => SokoniValidators.required(v),
              ),
              const SizedBox(height: SokoniDimens.space16),
              TextFormField(
                controller: _handleController,
                decoration: InputDecoration(
                  labelText: l10n.onboardingHandle,
                  hintText: l10n.onboardingHandleHint,
                  prefixText: '@',
                  suffixIcon: _CheckIcon(status: _handleStatus),
                ),
                validator: SokoniValidators.handle,
                onChanged: _onHandleChanged,
              ),
              if (_handleStatus == _CheckStatus.problem)
                Padding(
                  padding: const EdgeInsets.only(top: SokoniDimens.space4),
                  child: Text(l10n.createAccountHandleTaken, style: const TextStyle(color: SokoniColors.danger, fontSize: 12)),
                )
              else if (_handleStatus == _CheckStatus.ok)
                Padding(
                  padding: const EdgeInsets.only(top: SokoniDimens.space4),
                  child: Text(l10n.createAccountHandleAvailable, style: const TextStyle(color: SokoniColors.success, fontSize: 12)),
                ),
              const SizedBox(height: SokoniDimens.space16),
              Consumer(
                builder: (context, ref, _) {
                  final categoriesAsync = ref.watch(topLevelCategoriesProvider);
                  return categoriesAsync.when(
                    loading: () => const LinearProgressIndicator(),
                    error: (e, _) => Text('$e', style: const TextStyle(color: SokoniColors.danger)),
                    data: (categories) => DropdownButtonFormField<int>(
                      initialValue: _categoryId,
                      decoration: InputDecoration(labelText: l10n.onboardingCategory),
                      items: [
                        for (final c in categories) DropdownMenuItem(value: c.id, child: Text(c.name(locale))),
                      ],
                      onChanged: (value) => setState(() => _categoryId = value),
                      validator: (v) => v == null ? l10n.onboardingCategory : null,
                    ),
                  );
                },
              ),
              const SizedBox(height: SokoniDimens.space16),
              TextFormField(
                controller: _regionController,
                decoration: InputDecoration(labelText: l10n.onboardingRegion),
                validator: (v) => SokoniValidators.required(v),
              ),
              const SizedBox(height: SokoniDimens.space16),
              TextFormField(
                controller: _districtController,
                decoration: InputDecoration(labelText: l10n.onboardingDistrict),
                validator: (v) => SokoniValidators.required(v),
              ),
              const SizedBox(height: SokoniDimens.space16),
              TextFormField(
                controller: _addressController,
                decoration: InputDecoration(labelText: l10n.onboardingAddress),
                validator: (v) => SokoniValidators.required(v),
              ),
              const SizedBox(height: SokoniDimens.space16),
              TextFormField(
                controller: _whatsappController,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(labelText: l10n.onboardingWhatsapp),
              ),
            ],
            const SizedBox(height: SokoniDimens.space24),
            FilledButton(
              onPressed: _submitting ? null : () => _submit(isSeller),
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.onboardingNext),
            ),
          ],
        ),
      ),
    );
  }
}

class _CheckIcon extends StatelessWidget {
  const _CheckIcon({required this.status});

  final _CheckStatus status;

  @override
  Widget build(BuildContext context) {
    return switch (status) {
      // Deliberately the same as idle — a fail-open outcome must never
      // look alarming or block-worthy; see _CheckStatus.unknown's docs.
      _CheckStatus.idle || _CheckStatus.unknown => const SizedBox.shrink(),
      _CheckStatus.checking => const Padding(
        padding: EdgeInsets.all(12),
        child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
      ),
      _CheckStatus.ok => const Icon(Icons.check_circle_rounded, color: SokoniColors.success),
      _CheckStatus.problem => const Icon(Icons.error_rounded, color: SokoniColors.danger),
    };
  }
}
