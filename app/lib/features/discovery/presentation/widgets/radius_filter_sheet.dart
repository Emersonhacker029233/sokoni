import 'package:flutter/material.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../providers/discovery_providers.dart';

/// Radius picker (1/5/10/25km/All — CLAUDE.md feature 1), as a bottom sheet.
Future<RadiusPreset?> showRadiusFilterSheet(BuildContext context, RadiusPreset current) {
  return showSokoniBottomSheet<RadiusPreset>(
    context: context,
    initialChildSize: 0.4,
    builder: (context) => _RadiusFilterContent(current: current),
  );
}

class _RadiusFilterContent extends StatelessWidget {
  const _RadiusFilterContent({required this.current});

  final RadiusPreset current;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    String labelFor(RadiusPreset preset) {
      return preset.km == null ? l10n.radiusAll : l10n.radiusKm(preset.km!.toStringAsFixed(0));
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.radiusLabel, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        RadioGroup<RadiusPreset>(
          groupValue: current,
          onChanged: (value) => Navigator.of(context).pop(value),
          child: Column(
            children: [
              for (final preset in RadiusPreset.values)
                RadioListTile<RadiusPreset>(
                  value: preset,
                  // Yellow, not sokoniBlack: same "active/selected" accent
                  // language as the chip selectedColor elsewhere, and
                  // unlike black it doesn't need a light/dark branch to
                  // stay visible on both surfaces — sokoniBlack was
                  // rendering near-invisible against a dark-mode sheet.
                  // See DECISIONS.md.
                  activeColor: SokoniColors.sokoniYellow,
                  title: Text(labelFor(preset)),
                ),
            ],
          ),
        ),
      ],
    );
  }
}
