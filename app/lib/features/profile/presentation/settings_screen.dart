import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_mode_controller.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/validators.dart';
import '../../../data/models/user.dart';
import '../../../shared/widgets/sokoni_avatar.dart';
import '../../auth/presentation/auth_entry_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import 'change_phone_sheet.dart';

/// Part 4 (client feedback): "Settings currently offers only full name,
/// email and language ... build it out into a real account area", grouped
/// into clear sections with headers, both languages. Mirrors the
/// website's own `/account/settings` page's *fields*, but this screen is
/// the app's single account hub — the website spreads the equivalent
/// across several separate pages.
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  bool _initialized = false;
  bool _saving = false;
  bool _resending = false;
  bool _uploadingAvatar = false;
  String? _error;
  String? _status;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
      _status = null;
    });
    try {
      await ref
          .read(authRepositoryProvider)
          .updateProfile(
            name: _nameController.text.trim(),
            email: _emailController.text.trim().isEmpty ? null : _emailController.text.trim(),
          );
      ref.invalidate(currentUserProvider);
      if (mounted) setState(() => _status = AppLocalizations.of(context).settingsSaved);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _resendVerification() async {
    setState(() {
      _resending = true;
      _error = null;
      _status = null;
    });
    try {
      await ref.read(authRepositoryProvider).resendVerificationEmail();
      if (mounted) setState(() => _status = AppLocalizations.of(context).settingsVerificationResent);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  Future<void> _pickAvatar(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 90);
    if (picked == null) return;

    setState(() => _uploadingAvatar = true);
    try {
      final targetDir = await getTemporaryDirectory();
      final targetPath = p.join(targetDir.path, 'avatar_${DateTime.now().millisecondsSinceEpoch}.jpg');
      final compressed = await FlutterImageCompress.compressAndGetFile(
        picked.path,
        targetPath,
        quality: 85,
        minWidth: 512,
        minHeight: 512,
      );
      await ref.read(authRepositoryProvider).uploadAvatar(imagePath: compressed?.path ?? picked.path);
      ref.invalidate(currentUserProvider);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _uploadingAvatar = false);
    }
  }

  Future<void> _removeAvatar() async {
    setState(() => _uploadingAvatar = true);
    try {
      await ref.read(authRepositoryProvider).removeAvatar();
      ref.invalidate(currentUserProvider);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _uploadingAvatar = false);
    }
  }

  void _showAvatarSheet(String? currentAvatar) {
    final l10n = AppLocalizations.of(context);
    showModalBottomSheet<void>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: Text(l10n.productFormAddPhotoCamera),
              onTap: () {
                Navigator.of(sheetContext).pop();
                _pickAvatar(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: Text(l10n.productFormAddPhotoGallery),
              onTap: () {
                Navigator.of(sheetContext).pop();
                _pickAvatar(ImageSource.gallery);
              },
            ),
            if (currentAvatar != null)
              ListTile(
                leading: const Icon(Icons.delete_outline_rounded, color: SokoniColors.danger),
                title: Text(l10n.settingsRemovePhoto, style: const TextStyle(color: SokoniColors.danger)),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  _removeAvatar();
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final userAsync = ref.watch(currentUserProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.settingsTitle)),
      body: userAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text('$error')),
        data: (user) {
          if (!_initialized) {
            _nameController.text = user.name;
            _emailController.text = user.email ?? '';
            _initialized = true;
          }

          return ListView(
            padding: const EdgeInsets.all(SokoniDimens.space20),
            children: [
              _SectionHeader(l10n.settingsSectionProfile),
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: GestureDetector(
                        onTap: _uploadingAvatar ? null : () => _showAvatarSheet(user.avatar),
                        child: Stack(
                          alignment: Alignment.center,
                          children: [
                            SokoniAvatar(imageUrl: user.avatar, radius: 40, fallbackIcon: Icons.person_rounded),
                            if (_uploadingAvatar)
                              const CircularProgressIndicator()
                            else
                              const Align(
                                alignment: Alignment.bottomRight,
                                child: CircleAvatar(radius: 14, child: Icon(Icons.edit_rounded, size: 14)),
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: SokoniDimens.space20),
                    TextFormField(
                      controller: _nameController,
                      decoration: InputDecoration(labelText: l10n.settingsNameLabel),
                      validator: (v) => v == null || v.trim().isEmpty ? l10n.settingsNameLabel : null,
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    // Part 4 (client feedback): "phone number (display
                    // only, changing it needs re-verification)".
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                l10n.settingsPhoneLabel,
                                style: Theme.of(
                                  context,
                                ).textTheme.bodySmall?.copyWith(color: SokoniColors.sokoniBlack.withValues(alpha: 0.6)),
                              ),
                              Text(
                                user.phone != null ? SokoniFormat.phoneLocal(user.phone!) : '—',
                                style: Theme.of(context).textTheme.bodyLarge,
                              ),
                            ],
                          ),
                        ),
                        TextButton(
                          onPressed: () => showChangePhoneSheet(context),
                          child: Text(l10n.settingsPhoneChange),
                        ),
                      ],
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      decoration: InputDecoration(labelText: l10n.settingsEmailLabel, hintText: l10n.settingsEmailHint),
                      validator: SokoniValidators.optionalEmail,
                    ),
                    if (user.email != null) ...[
                      const SizedBox(height: SokoniDimens.space8),
                      Row(
                        children: [
                          Icon(
                            user.emailVerified ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                            size: 16,
                            color: user.emailVerified ? SokoniColors.success : SokoniColors.sokoniBlack.withValues(alpha: 0.5),
                          ),
                          const SizedBox(width: SokoniDimens.space4),
                          Expanded(
                            child: Text(
                              user.emailVerified ? l10n.settingsEmailVerified : l10n.settingsEmailUnverified,
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ),
                        ],
                      ),
                      if (!user.emailVerified)
                        Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton(
                            onPressed: _resending ? null : _resendVerification,
                            child: Text(l10n.settingsResendVerification),
                          ),
                        ),
                    ],
                    if (_error != null) ...[
                      const SizedBox(height: SokoniDimens.space8),
                      Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
                    ],
                    if (_status != null) ...[
                      const SizedBox(height: SokoniDimens.space8),
                      Text(_status!, style: const TextStyle(color: SokoniColors.success)),
                    ],
                    const SizedBox(height: SokoniDimens.space16),
                    FilledButton(
                      onPressed: _saving ? null : _save,
                      child: _saving
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text(l10n.settingsSave),
                    ),
                  ],
                ),
              ),

              _SectionHeader(l10n.settingsSectionAppearance),
              const _AppearanceSection(),

              _SectionHeader(l10n.settingsSectionLanguage),
              const _LanguageRow(),

              _SectionHeader(l10n.settingsSectionNotifications),
              _NotificationsSection(user: user),

              _SectionHeader(l10n.settingsSectionSecurity),
              _SecuritySection(user: user),

              _SectionHeader(l10n.settingsSectionAccount),
              const _AccountSection(),

              _SectionHeader(l10n.settingsSectionSupport),
              const _SupportLegalSection(),
              _SectionHeader(l10n.settingsSectionDangerZone),
              const _DangerZoneSection(),
            ],
          );
        },
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: SokoniDimens.space24, bottom: SokoniDimens.space12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(
              context,
            ).textTheme.labelLarge?.copyWith(color: SokoniColors.sokoniBlack.withValues(alpha: 0.5)),
          ),
          const SizedBox(height: SokoniDimens.space8),
          const Divider(height: 1),
        ],
      ),
    );
  }
}

/// Part 4 (client feedback): "Light, Dark, and System theme — the app
/// already has a dark theme with no way to choose it."
class _AppearanceSection extends ConsumerWidget {
  const _AppearanceSection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final mode = ref.watch(themeModeControllerProvider).value ?? ThemeMode.system;

    return SegmentedButton<ThemeMode>(
      segments: [
        ButtonSegment(value: ThemeMode.light, label: Text(l10n.settingsAppearanceLight)),
        ButtonSegment(value: ThemeMode.system, label: Text(l10n.settingsAppearanceSystem)),
        ButtonSegment(value: ThemeMode.dark, label: Text(l10n.settingsAppearanceDark)),
      ],
      selected: {mode},
      onSelectionChanged: (selection) => ref.read(themeModeControllerProvider.notifier).setThemeMode(selection.first),
    );
  }
}

/// Part 1 (language audit, client feedback): "Keep the existing language
/// switcher in settings... persist the choice across restarts and
/// across account switches." A minimal picker for now — Part 2 replaces
/// this with the full flag/dropdown/bottom-sheet component described
/// there; [LocaleController] (the actual default-resolution and
/// persistence logic) doesn't change when that happens.
class _LanguageRow extends ConsumerWidget {
  const _LanguageRow();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final saved = ref.watch(localeControllerProvider).value;
    final active = saved?.languageCode ?? resolveDeviceLocale(WidgetsBinding.instance.platformDispatcher.locales).languageCode;

    return Row(
      children: [
        Expanded(child: Text(l10n.settingsLanguageLabel, style: Theme.of(context).textTheme.titleSmall)),
        DropdownButton<String>(
          value: active,
          underline: const SizedBox.shrink(),
          items: [
            for (final code in supportedLanguageCodes)
              DropdownMenuItem(value: code, child: Text(languageEndonyms[code]!)),
          ],
          onChanged: (code) {
            if (code != null) ref.read(localeControllerProvider.notifier).setLocale(code);
          },
        ),
      ],
    );
  }
}

/// Part 4 (client feedback): "Toggles for orders, messages, offers from
/// followed shops, and marketing. Reflected server-side so push respects
/// them." Optimistic — flips immediately on tap, reverts + shows an
/// error only if the server call actually fails, since a toggle should
/// feel instant.
/// CLAUDE.md Part D 2.4 — "Require a code every time I sign in," off by
/// default. Same optimistic-toggle shape as [_NotificationsSection] below.
class _SecuritySection extends ConsumerStatefulWidget {
  const _SecuritySection({required this.user});

  final SokoniUser user;

  @override
  ConsumerState<_SecuritySection> createState() => _SecuritySectionState();
}

class _SecuritySectionState extends ConsumerState<_SecuritySection> {
  late bool _twoFactorEnabled = widget.user.twoFactorEnabled;
  String? _error;

  Future<void> _toggle(bool value) async {
    final previous = _twoFactorEnabled;
    setState(() {
      _twoFactorEnabled = value;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).updateTwoFactor(value);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _twoFactorEnabled = previous;
          _error = e.message;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      children: [
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l10n.settingsTwoFactorTitle),
          subtitle: Text(l10n.settingsTwoFactorSubtitle),
          value: _twoFactorEnabled,
          onChanged: _toggle,
        ),
        if (_error != null)
          Align(
            alignment: Alignment.centerLeft,
            child: Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          ),
      ],
    );
  }
}

class _NotificationsSection extends ConsumerStatefulWidget {
  const _NotificationsSection({required this.user});

  final SokoniUser user;

  @override
  ConsumerState<_NotificationsSection> createState() => _NotificationsSectionState();
}

class _NotificationsSectionState extends ConsumerState<_NotificationsSection> {
  late bool _orders = widget.user.notifyOrders;
  late bool _messages = widget.user.notifyMessages;
  late bool _offers = widget.user.notifyOffers;
  late bool _marketing = widget.user.notifyMarketing;
  String? _error;

  Future<void> _toggle({
    required bool value,
    required void Function(bool) apply,
    required Future<SokoniUser> Function() call,
  }) async {
    final previous = value;
    setState(() {
      apply(!previous);
      _error = null;
    });
    try {
      await call();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          apply(previous);
          _error = e.message;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final repo = ref.read(authRepositoryProvider);

    return Column(
      children: [
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l10n.settingsNotifyOrders),
          value: _orders,
          onChanged: (_) => _toggle(
            value: _orders,
            apply: (v) => _orders = v,
            call: () => repo.updateNotificationPreferences(notifyOrders: !_orders),
          ),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l10n.settingsNotifyMessages),
          value: _messages,
          onChanged: (_) => _toggle(
            value: _messages,
            apply: (v) => _messages = v,
            call: () => repo.updateNotificationPreferences(notifyMessages: !_messages),
          ),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l10n.settingsNotifyOffers),
          value: _offers,
          onChanged: (_) => _toggle(
            value: _offers,
            apply: (v) => _offers = v,
            call: () => repo.updateNotificationPreferences(notifyOffers: !_offers),
          ),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l10n.settingsNotifyMarketing),
          value: _marketing,
          onChanged: (_) => _toggle(
            value: _marketing,
            apply: (v) => _marketing = v,
            call: () => repo.updateNotificationPreferences(notifyMarketing: !_marketing),
          ),
        ),
        if (_error != null)
          Align(
            alignment: Alignment.centerLeft,
            child: Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          ),
      ],
    );
  }
}

/// Part 4 (client feedback): "Switch account, add account, sign out;
/// saved items; my orders; Start selling, or My Shop for sellers." Same
/// switcher UI/logic as ProfileScreen's own account section — duplicated
/// rather than shared, since ProfileScreen's version is `private` to that
/// file and Settings is a distinct entry point a user may reach directly.
class _AccountSection extends ConsumerWidget {
  const _AccountSection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final userAsync = ref.watch(currentUserProvider);
    final accountsAsync = ref.watch(storedAccountsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        accountsAsync.maybeWhen(
          data: (accounts) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final account in accounts)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: SokoniAvatar(imageUrl: account.avatar, radius: 20, fallbackIcon: Icons.person_rounded),
                  title: Text(account.name),
                  subtitle: account.handle != null ? Text('@${account.handle}') : null,
                  trailing: userAsync.value?.id == account.userId
                      ? Chip(
                          label: Text(l10n.profileAccountCurrent, style: const TextStyle(color: SokoniColors.onYellow)),
                          visualDensity: VisualDensity.compact,
                          backgroundColor: SokoniColors.sokoniYellow,
                          side: BorderSide.none,
                        )
                      : const Icon(Icons.chevron_right_rounded),
                  onTap: userAsync.value?.id == account.userId
                      ? null
                      : () => ref.read(authStateProvider.notifier).switchAccount(account.userId),
                ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const CircleAvatar(child: Icon(Icons.add_rounded)),
                title: Text(l10n.profileAddAccount),
                onTap: () => showAuthEntrySheet(context),
              ),
            ],
          ),
          orElse: () => const SizedBox.shrink(),
        ),
        const Divider(),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.favorite_border_rounded),
          title: Text(l10n.favoritesTitle),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push(SokoniRoutes.favorites),
        ),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.receipt_long_outlined),
          title: Text(l10n.ordersMyOrdersTab),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push(SokoniRoutes.orders),
        ),
        if (userAsync.value != null)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.storefront_outlined),
            title: Text(userAsync.value!.isSeller ? l10n.myShopTitle : l10n.profileStartSelling),
            trailing: const Icon(Icons.chevron_right_rounded),
            // Not push(): SokoniRoutes.sell is a StatefulShellRoute
            // branch, same reasoning as ProfileScreen's own button.
            onTap: () => userAsync.value!.isSeller
                ? context.go(SokoniRoutes.sell)
                : context.push(SokoniRoutes.sellerOnboarding),
          ),
        const SizedBox(height: SokoniDimens.space8),
        TextButton(
          onPressed: () => ref.read(authStateProvider.notifier).signOut(),
          child: Text(l10n.profileSignOut),
        ),
      ],
    );
  }
}

/// Part 4 (client feedback): "Help, contact, Terms, Privacy, app version."
class _SupportLegalSection extends StatelessWidget {
  const _SupportLegalSection();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.help_outline_rounded),
          title: Text(l10n.settingsHelp),
          trailing: const Icon(Icons.open_in_new_rounded, size: 18),
          onTap: () => launchUrl(Uri.parse('https://sokoni.co.tz/how-it-works'), mode: LaunchMode.externalApplication),
        ),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.mail_outline_rounded),
          title: Text(l10n.settingsContact),
          onTap: () => launchUrl(Uri.parse('mailto:support@sokoni.co.tz')),
        ),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.description_outlined),
          title: Text(l10n.legalTermsTitle),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push(SokoniRoutes.terms),
        ),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.privacy_tip_outlined),
          title: Text(l10n.legalPrivacyTitle),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push(SokoniRoutes.privacy),
        ),
        FutureBuilder<PackageInfo>(
          future: PackageInfo.fromPlatform(),
          builder: (context, snapshot) {
            final info = snapshot.data;
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.info_outline_rounded),
              title: Text(l10n.settingsAppVersion),
              trailing: Text(info == null ? '' : '${info.version}+${info.buildNumber}'),
            );
          },
        ),
      ],
    );
  }
}

/// Apple Guideline 5.1.1(v): "if a user can create an account in the app,
/// they can delete it in the app" — not by email, not through a website.
/// A real, irreversible server-side mutation (AccountDeletionService), so
/// this goes through `authRepositoryProvider` directly rather than
/// `AuthStateController` (unlike sign-out/switch-account, which only ever
/// touch local storage) — a failure must surface as a real error, never
/// be swallowed into "signed out locally while the account still exists."
class _DangerZoneSection extends ConsumerStatefulWidget {
  const _DangerZoneSection();

  @override
  ConsumerState<_DangerZoneSection> createState() => _DangerZoneSectionState();
}

class _DangerZoneSectionState extends ConsumerState<_DangerZoneSection> {
  bool _deleting = false;
  String? _error;

  Future<void> _confirmAndDelete() async {
    final l10n = AppLocalizations.of(context);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.settingsDeleteAccountConfirmTitle),
        content: Text(l10n.settingsDeleteAccountConfirmBody),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.commonCancel)),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(l10n.settingsDeleteAccount, style: const TextStyle(color: SokoniColors.danger)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() {
      _deleting = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).deleteAccount();
      if (!mounted) return;
      await ref.read(authStateProvider.notifier).onAccountDeleted();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.delete_forever_outlined, color: SokoniColors.danger),
          title: Text(l10n.settingsDeleteAccount, style: const TextStyle(color: SokoniColors.danger)),
          subtitle: Text(l10n.settingsDeleteAccountSubtitle),
          trailing: _deleting
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : null,
          onTap: _deleting ? null : _confirmAndDelete,
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: SokoniDimens.space8),
            child: Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          ),
      ],
    );
  }
}
