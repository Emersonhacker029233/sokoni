import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/driver_profile.dart';
import '../../shared/labels.dart';
import '../../shared/widgets/common.dart';
import '../home/work_controller.dart';
import '../onboarding/documents_list.dart';
import '../onboarding/vehicle_form.dart';
import 'profile_controller.dart';
import 'settings_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final state = ref.watch(profileProvider).value;
    final profile = state?.profile;

    return Scaffold(
      appBar: AppBar(title: Text(l.profileTitle)),
      body: profile == null
          ? const SizedBox.shrink()
          : RefreshIndicator(
              onRefresh: () => ref.read(profileProvider.notifier).refresh(),
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space8),
                children: [
                  if (state!.fromCache) NoticeBanner(icon: Icons.cloud_off_rounded, text: l.showingSaved),
                  Padding(
                    padding: const EdgeInsets.all(SokoniDimens.space16),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 36,
                          backgroundColor: theme.colorScheme.surfaceContainerHighest,
                          foregroundImage: profile.photoUrl == null ? null : NetworkImage(profile.photoUrl!),
                          child: Text(profile.name.isEmpty ? '?' : profile.name[0].toUpperCase(), style: theme.textTheme.titleLarge),
                        ),
                        const SizedBox(width: SokoniDimens.space16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(profile.name, style: theme.textTheme.titleLarge),
                              if (profile.phone != null) Text(DriverFormat.phoneLocal(profile.phone!), style: theme.textTheme.bodySmall),
                              const SizedBox(height: 6),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: [
                                  Pill(label: driverStatusLabel(l, profile), highlight: profile.isApproved),
                                  Pill(
                                    icon: Icons.star_rounded,
                                    label: profile.ratingCount == 0 || profile.ratingAvg == null
                                        ? l.noRatingYet
                                        : l.ratingValue(profile.ratingAvg!.toStringAsFixed(1)),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
                    child: Text(l.jobsCompleted(profile.jobsCompleted), style: theme.textTheme.bodyMedium),
                  ),
                  const SizedBox(height: SokoniDimens.space16),
                  _SectionTitle(l.vehicleSection),
                  if (profile.vehicle != null)
                    ListTile(
                      leading: Icon(vehicleIcon(profile.vehicle!.type)),
                      title: Text(
                        [vehicleLabel(l, profile.vehicle!.type), profile.vehicle!.plateNumber].whereType<String>().join(' · '),
                      ),
                      subtitle: Text(
                        [profile.vehicle!.make, profile.vehicle!.colour].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
                      ),
                      trailing: Pill(
                        label: profile.vehicle!.verified ? l.vehicleVerified : l.vehicleNotVerified,
                        color: profile.vehicle!.verified ? SokoniColors.success.withValues(alpha: 0.2) : null,
                      ),
                    ),
                  ListTile(
                    leading: const Icon(Icons.swap_horiz_rounded),
                    title: Text(l.changeVehicle),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () => _changeVehicle(context, ref, profile),
                  ),
                  _SectionTitle(l.documentsSection),
                  ListTile(
                    leading: const Icon(Icons.folder_open_outlined),
                    title: Text(l.updateDocuments),
                    subtitle: profile.licenceExpired ? Text(l.licenceExpiredTitle, style: const TextStyle(color: SokoniColors.danger)) : null,
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () => _documents(context, ref),
                  ),
                  const Divider(),
                  const SettingsSection(),
                ],
              ),
            ),
    );
  }

  Future<void> _changeVehicle(BuildContext context, WidgetRef ref, DriverProfile profile) async {
    final l = AppLocalizations.of(context);
    // The API refuses with a localised busy_on_job while a delivery is open.
    final formKey = GlobalKey<FormState>();
    DriverVehicle? vehicle = profile.vehicle;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) {
        var saving = false;
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) => Padding(
            padding: EdgeInsets.fromLTRB(
              SokoniDimens.space24,
              0,
              SokoniDimens.space24,
              MediaQuery.viewInsetsOf(sheetContext).bottom + SokoniDimens.space24,
            ),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(l.changeVehicle, style: Theme.of(sheetContext).textTheme.titleLarge),
                  const SizedBox(height: SokoniDimens.space8),
                  NoticeBanner(icon: Icons.info_outline_rounded, text: l.changeVehicleWarning),
                  const SizedBox(height: SokoniDimens.space16),
                  VehicleForm(formKey: formKey, initial: profile.vehicle, onChanged: (v) => vehicle = v),
                  const SizedBox(height: SokoniDimens.space24),
                  PrimaryButton(
                    label: l.save,
                    loading: saving,
                    onPressed: () async {
                      if (!formKey.currentState!.validate() || vehicle == null) return;
                      setSheetState(() => saving = true);
                      try {
                        final v = vehicle!;
                        final saved = await ref.read(driverRepositoryProvider).saveVehicle(
                              type: v.type,
                              plateNumber: v.plateNumber,
                              make: v.make,
                              colour: v.colour,
                            );
                        ref.read(profileProvider.notifier).set(saved);
                        if (!saved.canGoOnline && ref.read(workProvider).online) {
                          await ref.read(workProvider.notifier).goOffline();
                        }
                        if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                      } catch (e) {
                        if (sheetContext.mounted) showError(sheetContext, e);
                      } finally {
                        if (sheetContext.mounted) setSheetState(() => saving = false);
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Future<void> _documents(BuildContext context, WidgetRef ref) {
    return Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => Consumer(
          builder: (context, ref, _) {
            final profile = ref.watch(profileProvider).value?.profile;
            return Scaffold(
              appBar: AppBar(title: Text(AppLocalizations.of(context).documentsSection)),
              body: profile == null
                  ? const SizedBox.shrink()
                  : ListView(padding: const EdgeInsets.all(SokoniDimens.space24), children: [DocumentsList(profile: profile)]),
            );
          },
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(SokoniDimens.space16, SokoniDimens.space16, SokoniDimens.space16, SokoniDimens.space4),
      child: Text(text, style: Theme.of(context).textTheme.titleMedium),
    );
  }
}
