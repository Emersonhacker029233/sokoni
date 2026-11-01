import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/driver_profile.dart';
import '../../shared/labels.dart';
import '../../shared/widgets/common.dart';
import '../profile/profile_controller.dart';
import 'documents_list.dart';
import 'vehicle_form.dart';

/// Version of the driver terms recorded with the acceptance timestamp.
const driverTermsVersion = 'driver-1.0';

/// Four steps: details, vehicle, documents, review. Every step is saved on
/// the server as soon as it's done, so the wizard reopens where the driver
/// stopped, even on another phone.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  static const _steps = 4;
  int? _step;
  bool _saving = false;

  final _detailsForm = GlobalKey<FormState>();
  final _vehicleForm = GlobalKey<FormState>();
  final _nida = TextEditingController();
  final _licence = TextEditingController();
  DateTime? _licenceExpiry;
  bool _termsAccepted = false;
  DriverVehicle? _vehicle;
  bool _prefilled = false;

  @override
  void dispose() {
    _nida.dispose();
    _licence.dispose();
    super.dispose();
  }

  int _firstIncompleteStep(DriverProfile? p) {
    if (p == null) return 0;
    if (p.vehicle == null) return 1;
    final needsDocs = p.missingRequirements.any((r) => r == 'photo' || r == 'national_id_image' || r == 'licence_image');
    return needsDocs ? 2 : 3;
  }

  void _prefill(DriverProfile? p) {
    if (_prefilled || p == null) return;
    _prefilled = true;
    _licence.text = p.licenceNumber ?? '';
    _licenceExpiry = p.licenceExpiresOn == null ? null : DateTime.tryParse(p.licenceExpiresOn!);
    _termsAccepted = p.termsAcceptedAt != null;
  }

  Future<void> _saveDetails(DriverProfile? profile) async {
    if (!_detailsForm.currentState!.validate()) return;
    final l = AppLocalizations.of(context);
    if (!_termsAccepted) {
      showMessage(context, l.termsRequired);
      return;
    }
    final nida = _nida.text.replaceAll(RegExp(r'\D'), '');
    final unchanged = profile != null &&
        nida.isEmpty &&
        _licence.text.trim() == (profile.licenceNumber ?? '') &&
        _isoDate(_licenceExpiry) == profile.licenceExpiresOn;
    if (unchanged) {
      _go(1);
      return;
    }
    if (nida.length != 20) {
      showMessage(context, l.nationalIdInvalid);
      return;
    }
    await _run(() async {
      final saved = await ref.read(driverRepositoryProvider).saveProfile(
            nationalIdNumber: nida,
            licenceNumber: _licence.text.trim().isEmpty ? null : _licence.text.trim(),
            licenceExpiresOn: _isoDate(_licenceExpiry),
            termsVersion: driverTermsVersion,
          );
      ref.read(profileProvider.notifier).set(saved);
      _nida.clear();
      _go(1);
    });
  }

  Future<void> _saveVehicle() async {
    if (!_vehicleForm.currentState!.validate() || _vehicle == null) return;
    final v = _vehicle!;
    await _run(() async {
      final saved = await ref.read(driverRepositoryProvider).saveVehicle(
            type: v.type,
            plateNumber: v.plateNumber,
            make: v.make,
            colour: v.colour,
          );
      ref.read(profileProvider.notifier).set(saved);
      _go(2);
    });
  }

  Future<void> _submit() async {
    await _run(() async {
      final saved = await ref.read(driverRepositoryProvider).submit();
      ref.read(profileProvider.notifier).set(saved);
      // The router moves an under-review profile to the status screen.
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _saving = true);
    try {
      await action();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _go(int step) => setState(() => _step = step);

  static String? _isoDate(DateTime? d) =>
      d == null ? null : '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final profile = ref.watch(profileProvider).value?.profile;
    _prefill(profile);
    final step = _step ??= _firstIncompleteStep(profile);
    final titles = [l.stepDetails, l.stepVehicle, l.stepDocuments, l.stepReview];

    return PopScope(
      canPop: step == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && step > 0) _go(step - 1);
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.onboardingTitle),
          leading: step > 0 ? IconButton(icon: const Icon(Icons.arrow_back_rounded), tooltip: l.back, onPressed: () => _go(step - 1)) : null,
          actions: [
            IconButton(
              tooltip: l.profileTitle,
              icon: const Icon(Icons.settings_outlined),
              onPressed: () => context.push('/settings'),
            ),
          ],
        ),
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(SokoniDimens.space24, 0, SokoniDimens.space24, SokoniDimens.space16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.stepOf(step + 1, _steps), style: theme.textTheme.bodySmall),
                    const SizedBox(height: 4),
                    Text(titles[step], style: theme.textTheme.titleLarge),
                    const SizedBox(height: SokoniDimens.space12),
                    TweenAnimationBuilder<double>(
                      tween: Tween(end: (step + 1) / _steps),
                      duration: const Duration(milliseconds: 280),
                      curve: Curves.easeOutCubic,
                      builder: (context, value, _) => LinearProgressIndicator(
                        value: value,
                        minHeight: 6,
                        borderRadius: BorderRadius.circular(3),
                        backgroundColor: theme.colorScheme.surfaceContainerHighest,
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 280),
                  switchInCurve: Curves.easeOutCubic,
                  transitionBuilder: (child, animation) => FadeTransition(
                    opacity: animation,
                    child: SlideTransition(
                      position: Tween(begin: const Offset(0.06, 0), end: Offset.zero).animate(animation),
                      child: child,
                    ),
                  ),
                  child: KeyedSubtree(
                    key: ValueKey(step),
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(SokoniDimens.space24, SokoniDimens.space8, SokoniDimens.space24, SokoniDimens.space24),
                      children: [
                        switch (step) {
                          0 => _details(l, profile),
                          1 => VehicleForm(formKey: _vehicleForm, initial: profile?.vehicle, onChanged: (v) => _vehicle = v),
                          2 => profile == null ? const SizedBox.shrink() : DocumentsList(profile: profile),
                          _ => _review(l, profile),
                        },
                      ],
                    ),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(SokoniDimens.space24, 0, SokoniDimens.space24, SokoniDimens.space16),
                child: switch (step) {
                  0 => PrimaryButton(label: l.continueLabel, loading: _saving, onPressed: () => _saveDetails(profile)),
                  1 => PrimaryButton(label: l.continueLabel, loading: _saving, onPressed: _saveVehicle),
                  2 => PrimaryButton(label: l.continueLabel, onPressed: () => _go(3)),
                  _ => PrimaryButton(
                      label: l.submitForReview,
                      loading: _saving,
                      onPressed: profile?.canSubmit == true ? _submit : null,
                    ),
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _details(AppLocalizations l, DriverProfile? profile) {
    final theme = Theme.of(context);
    return Form(
      key: _detailsForm,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextFormField(
            controller: _nida,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9 -]')), LengthLimitingTextInputFormatter(26)],
            decoration: InputDecoration(
              labelText: l.nationalIdLabel,
              hintText: l.nationalIdHint,
              helperText: profile?.nationalIdLast4 == null ? null : l.nationalIdSaved(profile!.nationalIdLast4!),
            ),
            validator: (v) {
              final digits = (v ?? '').replaceAll(RegExp(r'\D'), '');
              if (digits.isEmpty && profile?.nationalIdLast4 != null) return null;
              return digits.length == 20 ? null : l.nationalIdInvalid;
            },
          ),
          const SizedBox(height: SokoniDimens.space16),
          TextFormField(
            controller: _licence,
            textCapitalization: TextCapitalization.characters,
            decoration: InputDecoration(labelText: l.licenceNumberLabel, helperText: l.licenceNotNeeded),
          ),
          const SizedBox(height: SokoniDimens.space16),
          InkWell(
            borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
            onTap: () async {
              final now = DateTime.now();
              final picked = await showDatePicker(
                context: context,
                firstDate: now.add(const Duration(days: 1)),
                lastDate: DateTime(now.year + 15),
                initialDate: _licenceExpiry ?? DateTime(now.year + 1, now.month, now.day),
              );
              if (picked != null) setState(() => _licenceExpiry = picked);
            },
            child: InputDecorator(
              decoration: InputDecoration(labelText: l.licenceExpiryLabel, suffixIcon: const Icon(Icons.event_outlined)),
              child: Text(
                _licenceExpiry == null ? l.licenceExpiryPick : DriverFormat.date(_licenceExpiry!, Localizations.localeOf(context).languageCode),
              ),
            ),
          ),
          const SizedBox(height: SokoniDimens.space24),
          CheckboxListTile(
            value: _termsAccepted,
            onChanged: (v) => setState(() => _termsAccepted = v ?? false),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            activeColor: SokoniColors.sokoniYellow,
            checkColor: SokoniColors.onYellow,
            title: Text(l.acceptTerms, style: theme.textTheme.bodyMedium),
            subtitle: GestureDetector(
              onTap: () => context.push('/terms'),
              child: Text(l.readTerms, style: theme.textTheme.bodySmall?.copyWith(decoration: TextDecoration.underline)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _review(AppLocalizations l, DriverProfile? profile) {
    final theme = Theme.of(context);
    if (profile == null) return const SizedBox.shrink();
    final missing = profile.missingRequirements;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (profile.isRejected && profile.rejectionReason != null) ...[
          NoticeBanner(icon: Icons.info_outline_rounded, text: l.rejectedReason(profile.rejectionReason!)),
          const SizedBox(height: SokoniDimens.space16),
        ],
        SectionCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(profile.name, style: theme.textTheme.titleMedium),
              if (profile.phone != null) Text(DriverFormat.phoneLocal(profile.phone!), style: theme.textTheme.bodySmall),
              const Divider(height: 24),
              if (profile.vehicle != null)
                Row(
                  children: [
                    Icon(vehicleIcon(profile.vehicle!.type)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        [vehicleLabel(l, profile.vehicle!.type), profile.vehicle!.plateNumber, profile.vehicle!.make, profile.vehicle!.colour]
                            .whereType<String>()
                            .where((s) => s.isNotEmpty)
                            .join(' · '),
                      ),
                    ),
                    TextButton(onPressed: () => _go(1), child: Text(l.edit)),
                  ],
                ),
              if (profile.nationalIdLast4 != null)
                Row(
                  children: [
                    const Icon(Icons.badge_outlined),
                    const SizedBox(width: 12),
                    Expanded(child: Text(l.nationalIdSaved(profile.nationalIdLast4!))),
                    TextButton(onPressed: () => _go(0), child: Text(l.edit)),
                  ],
                ),
            ],
          ),
        ),
        const SizedBox(height: SokoniDimens.space16),
        if (missing.isEmpty)
          Row(
            children: [
              const Icon(Icons.check_circle_rounded, color: SokoniColors.success),
              const SizedBox(width: 8),
              Expanded(child: Text(l.reviewReady, style: theme.textTheme.bodyMedium)),
            ],
          )
        else ...[
          Text(l.reviewMissing, style: theme.textTheme.titleMedium),
          const SizedBox(height: SokoniDimens.space8),
          for (final r in missing)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.radio_button_unchecked_rounded, color: SokoniColors.danger),
              title: Text(requirementLabel(l, r)),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => _go(switch (r) {
                'vehicle' => 1,
                'national_id_number' || 'licence_number' => 0,
                _ => 2,
              }),
            ),
        ],
      ],
    );
  }
}
