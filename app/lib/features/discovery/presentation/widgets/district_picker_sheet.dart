import 'package:flutter/material.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../../core/theme/dimens.dart';
import '../../providers/discovery_location.dart';

/// Region/district fallback when location permission is denied or GPS is
/// off (CLAUDE.md feature 1: "never block the app").
Future<String?> showDistrictPickerSheet(BuildContext context) {
  return showSokoniBottomSheet<String>(
    context: context,
    initialChildSize: 0.45,
    builder: (context) => const _DistrictPickerContent(),
  );
}

class _DistrictPickerContent extends StatelessWidget {
  const _DistrictPickerContent();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.locationChooseArea, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        for (final district in daresSalaamDistricts.keys)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(district),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => Navigator.of(context).pop(district),
          ),
      ],
    );
  }
}
