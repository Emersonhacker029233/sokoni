import 'package:flutter/material.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_state.dart';

/// Debug route rendering every themed component in both light and dark
/// theme on one scrollable page (Part 1 contrast audit) — reachable at
/// `/theme-audit`, regardless of the device's actual system theme
/// setting, so both can be checked without switching device settings
/// mid-review. Each section forces its own [ThemeData] via an explicit
/// [Theme] override (not just `themeMode`), including for the dialog/
/// bottom-sheet triggers, which otherwise resolve against the app's real
/// current theme rather than the section being demonstrated — see
/// DECISIONS.md.
class ThemeAuditScreen extends StatelessWidget {
  const ThemeAuditScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.themeAuditTitle)),
      body: ListView(
        children: [
          _ThemedSection(label: l10n.themeAuditLightSection, themeData: SokoniTheme.light),
          const Divider(height: 1),
          _ThemedSection(label: l10n.themeAuditDarkSection, themeData: SokoniTheme.dark),
        ],
      ),
    );
  }
}

class _ThemedSection extends StatefulWidget {
  const _ThemedSection({required this.label, required this.themeData});

  final String label;
  final ThemeData themeData;

  @override
  State<_ThemedSection> createState() => _ThemedSectionState();
}

class _ThemedSectionState extends State<_ThemedSection> {
  bool _chipSelected = true;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = widget.themeData;
    final textTheme = theme.textTheme;

    return Theme(
      data: theme,
      child: Material(
        color: theme.scaffoldBackgroundColor,
        child: Padding(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(widget.label, style: textTheme.headlineSmall),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditTypography, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              Text('Display large', style: textTheme.displayLarge),
              Text('Headline small', style: textTheme.headlineSmall),
              Text('Title large', style: textTheme.titleLarge),
              Text('Title medium', style: textTheme.titleMedium),
              Text('Title small', style: textTheme.titleSmall),
              Text('Body large', style: textTheme.bodyLarge),
              Text('Body medium', style: textTheme.bodyMedium),
              Text('Body small (muted)', style: textTheme.bodySmall),
              Text('Label large', style: textTheme.labelLarge),
              Text('Label small', style: textTheme.labelSmall),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditButtons, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              Wrap(
                spacing: SokoniDimens.space8,
                runSpacing: SokoniDimens.space8,
                children: [
                  FilledButton(onPressed: () {}, child: Text(l10n.themeAuditEnabled)),
                  const FilledButton(onPressed: null, child: Text('—')),
                  OutlinedButton(onPressed: () {}, child: Text(l10n.themeAuditEnabled)),
                  const OutlinedButton(onPressed: null, child: Text('—')),
                  TextButton(onPressed: () {}, child: Text(l10n.themeAuditEnabled)),
                  const TextButton(onPressed: null, child: Text('—')),
                ],
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditChips, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              Wrap(
                spacing: SokoniDimens.space8,
                children: [
                  ChoiceChip(
                    label: Text(l10n.themeAuditChipSelected),
                    selected: _chipSelected,
                    onSelected: (v) => setState(() => _chipSelected = v),
                  ),
                  ChoiceChip(
                    label: Text(l10n.themeAuditChipUnselected),
                    selected: !_chipSelected,
                    onSelected: (v) => setState(() => _chipSelected = !v),
                  ),
                ],
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditFields, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              TextField(decoration: InputDecoration(labelText: l10n.themeAuditFieldNormal)),
              const SizedBox(height: SokoniDimens.space12),
              TextField(
                decoration: InputDecoration(
                  labelText: l10n.themeAuditFieldError,
                  errorText: l10n.themeAuditFieldError,
                ),
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditCard, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(SokoniDimens.space16),
                  child: Text(l10n.themeAuditCardBody, style: textTheme.bodyMedium),
                ),
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditDialog, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              OutlinedButton(
                onPressed: () => showDialog<void>(
                  context: context,
                  // Explicit Theme wrapper, not just relying on ambient
                  // theme inheritance — showDialog's default root
                  // navigator resolves against the *app's real* current
                  // theme, not this section's forced override, which
                  // would make this button show the wrong theme's dialog
                  // whenever the device's actual system theme disagrees
                  // with which section triggered it.
                  builder: (dialogContext) => Theme(
                    data: theme,
                    child: AlertDialog(
                      title: Text(l10n.themeAuditDialog),
                      content: Text(l10n.themeAuditDialogBody),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.of(dialogContext).pop(),
                          child: Text(l10n.commonClose),
                        ),
                      ],
                    ),
                  ),
                ),
                child: Text(l10n.themeAuditOpenDialog),
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditSnackbar, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              // A real ScaffoldMessenger-triggered SnackBar would render
              // against the *ambient* Scaffold (the outer ThemeAuditScreen
              // one), not this section's forced theme — so this renders a
              // static preview built straight from snackBarTheme instead,
              // which is what actually needs auditing here.
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: SokoniDimens.space16,
                  vertical: SokoniDimens.space12,
                ),
                decoration: BoxDecoration(
                  color: theme.snackBarTheme.backgroundColor,
                  borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
                ),
                child: Text(l10n.themeAuditShowSnackbar, style: theme.snackBarTheme.contentTextStyle),
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditEmptyState, style: textTheme.titleMedium),
              SizedBox(
                height: 220,
                child: SokoniEmptyState(
                  icon: Icons.inbox_outlined,
                  title: l10n.themeAuditEmptyState,
                  message: l10n.themeAuditCardBody,
                ),
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditErrorState, style: textTheme.titleMedium),
              SizedBox(
                height: 220,
                child: SokoniErrorState(message: l10n.themeAuditErrorState, onRetry: () {}),
              ),
              const SizedBox(height: SokoniDimens.space20),

              Text(l10n.themeAuditNavBar, style: textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space8),
              Container(
                height: SokoniDimens.navBarHeight,
                decoration: BoxDecoration(
                  color: theme.scaffoldBackgroundColor,
                  border: Border(top: BorderSide(color: theme.colorScheme.outline)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: _NavBarPreviewIcon(
                        selected: true,
                        icon: Icons.home_rounded,
                        label: l10n.navHome,
                        theme: theme,
                      ),
                    ),
                    Expanded(
                      child: _NavBarPreviewIcon(
                        selected: false,
                        icon: Icons.search_rounded,
                        label: l10n.navSearch,
                        theme: theme,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: SokoniDimens.space32),
            ],
          ),
        ),
      ),
    );
  }
}

/// Static, non-animated stand-in for [AnimatedNavBar]'s selected/
/// unselected states — enough to audit the colours without wiring up the
/// full motion primitive here.
class _NavBarPreviewIcon extends StatelessWidget {
  const _NavBarPreviewIcon({
    required this.selected,
    required this.icon,
    required this.label,
    required this.theme,
  });

  final bool selected;
  final IconData icon;
  final String label;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final isDark = theme.brightness == Brightness.dark;
    final onSurface = isDark ? SokoniColors.darkOnSurface : SokoniColors.sokoniBlack;

    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          width: 36,
          height: 36,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? SokoniColors.sokoniYellow : Colors.transparent,
            shape: BoxShape.circle,
          ),
          child: Icon(
            icon,
            size: 24,
            color: selected ? SokoniColors.onYellow : onSurface.withValues(alpha: 0.5),
          ),
        ),
        if (selected) ...[
          const SizedBox(height: 2),
          Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: onSurface)),
        ],
      ],
    );
  }
}
