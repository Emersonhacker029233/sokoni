import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';

/// Each language listed in its own name, never translated.
Future<void> showLanguageSheet(BuildContext context, WidgetRef ref) {
  final current = ref.read(localeProvider).value?.languageCode ?? 'sw';
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final code in supportedLanguageCodes)
            ListTile(
              minTileHeight: SokoniDimens.minTapTarget + 12,
              title: Text(languageEndonyms[code]!),
              trailing: code == current ? const Icon(Icons.check_circle, color: SokoniColors.sokoniYellow) : null,
              onTap: () {
                Navigator.of(context).pop();
                ref.read(localeProvider.notifier).set(code);
              },
            ),
          const SizedBox(height: SokoniDimens.space8),
        ],
      ),
    ),
  );
}

/// App-bar action on the sign-in screens.
class LanguageButton extends ConsumerWidget {
  const LanguageButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final code = ref.watch(localeProvider).value?.languageCode ?? 'sw';
    return TextButton.icon(
      onPressed: () => showLanguageSheet(context, ref),
      icon: const Icon(Icons.translate_rounded, size: 18),
      label: Text(languageEndonyms[code]!),
    );
  }
}

/// Settings row in Profile.
class LanguageTile extends ConsumerWidget {
  const LanguageTile({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final code = ref.watch(localeProvider).value?.languageCode ?? 'sw';
    return ListTile(
      leading: const Icon(Icons.translate_rounded),
      title: Text(AppLocalizations.of(context).language),
      subtitle: Text(languageEndonyms[code]!),
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: () => showLanguageSheet(context, ref),
    );
  }
}
