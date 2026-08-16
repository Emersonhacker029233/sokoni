import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:video_compress/video_compress.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/product.dart';
import '../providers/social_providers.dart';
import 'widgets/my_product_picker_sheet.dart';

/// "Showcase"/"Onyesho" composer (CLAUDE.md Part 3) — a vertical
/// full-screen product video, always tied to one of the seller's own
/// Listings. Same pick-compress-thumbnail shape as ProductFormScreen's
/// video upload.
class ShowcaseComposerScreen extends ConsumerStatefulWidget {
  const ShowcaseComposerScreen({super.key});

  @override
  ConsumerState<ShowcaseComposerScreen> createState() => _ShowcaseComposerScreenState();
}

class _ShowcaseComposerScreenState extends ConsumerState<ShowcaseComposerScreen> {
  final _captionController = TextEditingController();
  Product? _product;
  String? _videoPath;
  String? _thumbPath;
  int _durationSeconds = 0;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _captionController.dispose();
    super.dispose();
  }

  Future<void> _pickProduct() async {
    final product = await showMyProductPickerSheet(context);
    if (product != null) setState(() => _product = product);
  }

  Future<void> _pickVideo() async {
    final picked = await ImagePicker().pickVideo(
      source: ImageSource.gallery,
      maxDuration: const Duration(seconds: 60),
    );
    if (picked == null) return;

    setState(() => _submitting = true);
    try {
      final info = await VideoCompress.compressVideo(picked.path, quality: VideoQuality.MediumQuality, deleteOrigin: false);
      final compressedPath = info?.path ?? picked.path;
      final durationMs = info?.duration ?? (await VideoCompress.getMediaInfo(picked.path)).duration ?? 0;
      final thumb = await VideoCompress.getFileThumbnail(compressedPath, quality: 70);

      setState(() {
        _videoPath = compressedPath;
        _thumbPath = thumb.path;
        _durationSeconds = (durationMs / 1000).round().clamp(1, 60);
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _submit() async {
    final product = _product;
    final videoPath = _videoPath;
    final thumbPath = _thumbPath;
    if (product == null || videoPath == null || thumbPath == null) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref
          .read(showcasesRepositoryProvider)
          .create(
            productId: product.id,
            videoPath: videoPath,
            thumbnailPath: thumbPath,
            durationSeconds: _durationSeconds,
            caption: _captionController.text.trim().isEmpty ? null : _captionController.text.trim(),
          );
      if (!mounted) return;
      ref.invalidate(showcaseFeedProvider);
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final product = _product;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.showcaseComposerTitle)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (product != null)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(product.title),
                trailing: TextButton(onPressed: _pickProduct, child: Text(l10n.showcaseComposerChooseProduct)),
              )
            else
              OutlinedButton.icon(
                onPressed: _pickProduct,
                icon: const Icon(Icons.inventory_2_outlined),
                label: Text(l10n.showcaseComposerChooseProduct),
              ),
            const SizedBox(height: SokoniDimens.space16),
            if (_thumbPath != null)
              GestureDetector(
                onTap: _submitting ? null : _pickVideo,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
                  child: Image.file(File(_thumbPath!), height: 180, width: double.infinity, fit: BoxFit.cover),
                ),
              )
            else
              OutlinedButton.icon(
                onPressed: _submitting ? null : _pickVideo,
                icon: const Icon(Icons.videocam_outlined),
                label: Text(l10n.showcaseComposerPickVideo),
              ),
            const SizedBox(height: SokoniDimens.space16),
            TextField(
              controller: _captionController,
              maxLength: 280,
              maxLines: 3,
              decoration: InputDecoration(hintText: l10n.showcaseComposerCaptionHint),
            ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: (_submitting || product == null || _videoPath == null) ? null : _submit,
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.showcaseComposerSubmit),
            ),
          ],
        ),
      ),
    );
  }
}
