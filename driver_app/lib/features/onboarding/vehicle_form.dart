import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/models/driver_profile.dart';
import '../../shared/labels.dart';

/// Vehicle type, plate, make and colour. Used by onboarding and by
/// Profile's "change vehicle".
class VehicleForm extends StatefulWidget {
  const VehicleForm({super.key, required this.formKey, this.initial, required this.onChanged});

  final GlobalKey<FormState> formKey;
  final DriverVehicle? initial;
  final ValueChanged<DriverVehicle> onChanged;

  @override
  State<VehicleForm> createState() => _VehicleFormState();
}

class _VehicleFormState extends State<VehicleForm> {
  late String _type = widget.initial?.type ?? 'boda_boda';
  late final _plate = TextEditingController(text: widget.initial?.plateNumber);
  late final _make = TextEditingController(text: widget.initial?.make);
  late final _colour = TextEditingController(text: widget.initial?.colour);

  @override
  void initState() {
    super.initState();
    for (final c in [_plate, _make, _colour]) {
      c.addListener(_emit);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _emit());
  }

  void _emit() {
    widget.onChanged(DriverVehicle(
      type: _type,
      plateNumber: _type == 'bicycle' ? null : _plate.text.trim(),
      make: _make.text.trim().isEmpty ? null : _make.text.trim(),
      colour: _colour.text.trim().isEmpty ? null : _colour.text.trim(),
    ));
  }

  @override
  void dispose() {
    _plate.dispose();
    _make.dispose();
    _colour.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Form(
      key: widget.formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.vehicleTypeLabel, style: theme.textTheme.titleMedium),
          const SizedBox(height: SokoniDimens.space12),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: SokoniDimens.space12,
            crossAxisSpacing: SokoniDimens.space12,
            childAspectRatio: 1.6,
            children: [
              for (final type in vehicleTypes)
                _TypeTile(
                  label: vehicleLabel(l, type),
                  icon: vehicleIcon(type),
                  selected: _type == type,
                  onTap: () {
                    setState(() => _type = type);
                    _emit();
                  },
                ),
            ],
          ),
          const SizedBox(height: SokoniDimens.space24),
          AnimatedSize(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            child: _type == 'bicycle'
                ? const SizedBox(width: double.infinity)
                : Padding(
                    padding: const EdgeInsets.only(bottom: SokoniDimens.space16),
                    child: TextFormField(
                      controller: _plate,
                      textCapitalization: TextCapitalization.characters,
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9 ]')), LengthLimitingTextInputFormatter(12)],
                      decoration: InputDecoration(labelText: l.plateLabel, hintText: l.plateHint),
                      validator: (v) => _type != 'bicycle' && (v ?? '').trim().length < 4 ? l.plateRequired : null,
                    ),
                  ),
          ),
          TextFormField(
            controller: _make,
            textCapitalization: TextCapitalization.words,
            decoration: InputDecoration(labelText: '${l.makeLabel} (${l.optional})', hintText: l.makeHint),
          ),
          const SizedBox(height: SokoniDimens.space16),
          TextFormField(
            controller: _colour,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(labelText: '${l.colourLabel} (${l.optional})'),
          ),
        ],
      ),
    );
  }
}

class _TypeTile extends StatelessWidget {
  const _TypeTile({required this.label, required this.icon, required this.selected, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      selected: selected,
      button: true,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOutCubic,
        decoration: BoxDecoration(
          color: selected ? SokoniColors.sokoniYellow : theme.colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          border: Border.all(color: selected ? SokoniColors.sokoniYellow : theme.colorScheme.outline),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          onTap: onTap,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 30, color: selected ? SokoniColors.onYellow : theme.colorScheme.onSurface),
              const SizedBox(height: 6),
              Text(
                label,
                style: theme.textTheme.labelLarge?.copyWith(color: selected ? SokoniColors.onYellow : theme.colorScheme.onSurface),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
