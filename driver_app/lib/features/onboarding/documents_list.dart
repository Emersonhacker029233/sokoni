import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/models/driver_profile.dart';
import '../../shared/photo_picker.dart';
import '../../shared/widgets/common.dart';
import '../profile/profile_controller.dart';

/// The three document photos, each uploaded the moment it's taken so a
/// dropped connection never loses more than one picture.
class DocumentsList extends ConsumerStatefulWidget {
  const DocumentsList({super.key, required this.profile});

  final DriverProfile profile;

  @override
  ConsumerState<DocumentsList> createState() => _DocumentsListState();
}

class _DocumentsListState extends ConsumerState<DocumentsList> {
  final _localPaths = <String, String>{};
  String? _uploading;
  double _progress = 0;

  Future<void> _pick(String field) async {
    final path = await pickPhoto(context, preferFront: field == 'photo');
    if (path == null || !mounted) return;
    setState(() {
      _uploading = field;
      _progress = 0;
    });
    try {
      final profile = await ref.read(driverRepositoryProvider).uploadDocuments(
        {field: path},
        onProgress: (p) {
          if (mounted) setState(() => _progress = p);
        },
      );
      _localPaths[field] = path;
      ref.read(profileProvider.notifier).set(profile);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _uploading = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final docs = widget.profile.documents;
    final bicycle = widget.profile.vehicle?.type == 'bicycle';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _DocTile(
          title: l.docPhoto,
          hint: l.docPhotoHint,
          icon: Icons.face_retouching_natural_outlined,
          uploaded: docs.photo,
          localPath: _localPaths['photo'],
          uploading: _uploading == 'photo',
          progress: _progress,
          onTap: _uploading == null ? () => _pick('photo') : null,
        ),
        const SizedBox(height: SokoniDimens.space12),
        _DocTile(
          title: l.docNationalId,
          hint: l.docNationalIdHint,
          icon: Icons.badge_outlined,
          uploaded: docs.nationalIdImage,
          localPath: _localPaths['national_id_image'],
          uploading: _uploading == 'national_id_image',
          progress: _progress,
          onTap: _uploading == null ? () => _pick('national_id_image') : null,
        ),
        const SizedBox(height: SokoniDimens.space12),
        _DocTile(
          title: l.docLicence,
          hint: bicycle ? l.licenceNotNeeded : l.docLicenceHint,
          icon: Icons.credit_card_outlined,
          uploaded: docs.licenceImage,
          localPath: _localPaths['licence_image'],
          uploading: _uploading == 'licence_image',
          progress: _progress,
          onTap: _uploading == null ? () => _pick('licence_image') : null,
        ),
        const SizedBox(height: SokoniDimens.space16),
        Row(
          children: [
            const Icon(Icons.lock_outline_rounded, size: 16),
            const SizedBox(width: 8),
            Expanded(child: Text(l.documentsPrivacy, style: Theme.of(context).textTheme.bodySmall)),
          ],
        ),
      ],
    );
  }
}

class _DocTile extends StatelessWidget {
  const _DocTile({
    required this.title,
    required this.hint,
    required this.icon,
    required this.uploaded,
    required this.localPath,
    required this.uploading,
    required this.progress,
    required this.onTap,
  });

  final String title;
  final String hint;
  final IconData icon;
  final bool uploaded;
  final String? localPath;
  final bool uploading;
  final double progress;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return SectionCard(
      onTap: onTap,
      child: Row(
        children: [
          if (localPath != null)
            LocalThumb(path: localPath!)
          else
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: uploaded ? SokoniColors.success.withValues(alpha: 0.12) : theme.colorScheme.surface,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: theme.colorScheme.outline),
              ),
              child: Icon(uploaded ? Icons.check_rounded : icon, color: uploaded ? SokoniColors.success : null),
            ),
          const SizedBox(width: SokoniDimens.space16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: theme.textTheme.titleMedium),
                const SizedBox(height: 2),
                if (uploading) ...[
                  Text(l.uploadingProgress((progress * 100).round()), style: theme.textTheme.bodySmall),
                  const SizedBox(height: 6),
                  LinearProgressIndicator(value: progress, minHeight: 4, borderRadius: BorderRadius.circular(2)),
                ] else
                  Text(uploaded ? l.docUploaded : hint, style: theme.textTheme.bodySmall?.copyWith(color: uploaded ? SokoniColors.success : null)),
              ],
            ),
          ),
          const SizedBox(width: SokoniDimens.space8),
          if (!uploading) Text(uploaded ? l.docReplace : l.docAdd, style: theme.textTheme.labelLarge),
        ],
      ),
    );
  }
}
