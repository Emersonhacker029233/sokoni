import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:video_compress/video_compress.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/product.dart';
import '../providers/social_providers.dart';
import 'widgets/my_product_picker_sheet.dart';

/// "Update"/"Taarifa" composer (CLAUDE.md Part 3) — a 24h shop notice.
/// Image or video, optional caption, optional attached Listing. Same
/// pick-compress-upload shape as ProductFormScreen's media step.
class UpdateComposerScreen extends ConsumerStatefulWidget {
  const UpdateComposerScreen({super.key});

  @override
  ConsumerState<UpdateComposerScreen> createState() => _UpdateComposerScreenState();
}

class _UpdateComposerScreenState extends ConsumerState<UpdateComposerScreen> {
  final _captionController = TextEditingController();
  Product? _attachedProduct;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _captionController.dispose();
    super.dispose();
  }

  Future<void> _postPhoto() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 90);
    if (picked == null) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final targetDir = await getTemporaryDirectory();
      final targetPath = p.join(targetDir.path, 'update_${DateTime.now().millisecondsSinceEpoch}.jpg');
      final compressed = await FlutterImageCompress.compressAndGetFile(
        picked.path,
        targetPath,
        quality: 85,
        minWidth: 1200,
        minHeight: 1200,
      );

      await ref
          .read(updatesRepositoryProvider)
          .postImage(
            imagePath: compressed?.path ?? picked.path,
            caption: _captionController.text.trim().isEmpty ? null : _captionController.text.trim(),
            productId: _attachedProduct?.id,
          );
      _onPosted();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _postVideo() async {
    final picked = await ImagePicker().pickVideo(
      source: ImageSource.gallery,
      maxDuration: const Duration(seconds: 60),
    );
    if (picked == null) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final info = await VideoCompress.compressVideo(picked.path, quality: VideoQuality.MediumQuality, deleteOrigin: false);
      final compressedPath = info?.path ?? picked.path;
      final thumb = await VideoCompress.getFileThumbnail(compressedPath, quality: 70);

      await ref
          .read(updatesRepositoryProvider)
          .postVideo(
            videoPath: compressedPath,
            thumbnailPath: thumb.path,
            caption: _captionController.text.trim().isEmpty ? null : _captionController.text.trim(),
            productId: _attachedProduct?.id,
          );
      _onPosted();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _onPosted() {
    if (!mounted) return;
    ref.invalidate(updatesTrayProvider);
    Navigator.of(context).pop();
  }

  Future<void> _pickAttachedProduct() async {
    final product = await showMyProductPickerSheet(context);
    if (product != null) setState(() => _attachedProduct = product);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.updateComposerTitle)),
      body: Padding(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: _captionController,
              maxLength: 280,
              maxLines: 3,
              decoration: InputDecoration(hintText: l10n.updateComposerCaptionHint),
            ),
            const SizedBox(height: SokoniDimens.space12),
            if (_attachedProduct != null)
              InputChip(
                label: Text(_attachedProduct!.title),
                onDeleted: () => setState(() => _attachedProduct = null),
              )
            else
              OutlinedButton.icon(
                onPressed: _submitting ? null : _pickAttachedProduct,
                icon: const Icon(Icons.inventory_2_outlined),
                label: Text(l10n.updateComposerAttachProduct),
              ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const Spacer(),
            if (_submitting)
              const Center(child: CircularProgressIndicator())
            else
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _postPhoto,
                      icon: const Icon(Icons.photo_camera_outlined),
                      label: Text(l10n.updateComposerPickPhoto),
                    ),
                  ),
                  const SizedBox(width: SokoniDimens.space12),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _postVideo,
                      icon: const Icon(Icons.videocam_outlined),
                      label: Text(l10n.updateComposerPickVideo),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
