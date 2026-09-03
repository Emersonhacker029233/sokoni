import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:video_compress/video_compress.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/validators.dart';
import '../../../../data/models/category.dart';
import '../../../../data/models/product.dart';
import '../../../../data/models/product_media.dart';
import '../../../discovery/providers/discovery_providers.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// Create/edit a product, including its media (CLAUDE.md feature 7: up to
/// 8 photos/video mixed, video max 60s compressed client-side to ~720p).
/// Media can only be attached once the product exists server-side (the
/// upload endpoint is nested under a product id), so on create the form
/// saves the product first and then unlocks the media section in place —
/// no separate "step 2" screen needed.
class ProductFormScreen extends ConsumerStatefulWidget {
  const ProductFormScreen({this.productId, super.key});

  final int? productId;

  @override
  ConsumerState<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends ConsumerState<ProductFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _priceController = TextEditingController();
  final _stockController = TextEditingController(text: '1');
  int? _categoryId;
  String _condition = 'new';

  Product? _product;
  bool _loading = false;
  bool _submitting = false;
  bool _mediaBusy = false;
  String? _error;

  bool get _isEditing => widget.productId != null;

  @override
  void initState() {
    super.initState();
    if (widget.productId != null) _loadExisting(widget.productId!);
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _priceController.dispose();
    _stockController.dispose();
    super.dispose();
  }

  Future<void> _loadExisting(int id) async {
    setState(() => _loading = true);
    try {
      final product = await ref.read(productRepositoryProvider).product(id);
      setState(() {
        _product = product;
        _titleController.text = product.title;
        _descriptionController.text = product.description ?? '';
        _priceController.text = product.price.toString();
        _stockController.text = product.stock.toString();
        _condition = product.condition;
        _categoryId = product.category?.id;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false) || _categoryId == null) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = ref.read(productRepositoryProvider);
      final price = int.parse(_priceController.text.replaceAll(',', ''));
      final stock = int.parse(_stockController.text);
      final Product saved;
      if (_product == null) {
        saved = await repo.createProduct(
          categoryId: _categoryId!,
          title: _titleController.text.trim(),
          description: _descriptionController.text.trim().isEmpty ? null : _descriptionController.text.trim(),
          price: price,
          stock: stock,
          condition: _condition,
        );
      } else {
        saved = await repo.updateProduct(
          productId: _product!.id,
          categoryId: _categoryId,
          title: _titleController.text.trim(),
          description: _descriptionController.text.trim(),
          price: price,
          stock: stock,
          condition: _condition,
        );
      }
      setState(() => _product = saved);
      if (mounted && !_isEditing) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).productFormSavedAddMedia)));
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _addPhoto(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 90);
    if (picked == null || _product == null) return;
    setState(() => _mediaBusy = true);
    try {
      final targetDir = await getTemporaryDirectory();
      final targetPath = p.join(targetDir.path, 'product_${DateTime.now().millisecondsSinceEpoch}.jpg');
      final compressed = await FlutterImageCompress.compressAndGetFile(
        picked.path,
        targetPath,
        quality: 85,
        minWidth: 1600,
        minHeight: 1600,
      );
      final media = await ref
          .read(productRepositoryProvider)
          .uploadImageMedia(productId: _product!.id, imagePath: compressed?.path ?? picked.path);
      setState(() => _product = _product!.copyWith(media: [..._product!.media, media]));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _mediaBusy = false);
    }
  }

  Future<void> _addVideo() async {
    final picked = await ImagePicker().pickVideo(source: ImageSource.gallery, maxDuration: const Duration(seconds: 60));
    if (picked == null || _product == null) return;
    setState(() => _mediaBusy = true);
    try {
      final info = await VideoCompress.compressVideo(
        picked.path,
        quality: VideoQuality.MediumQuality,
        deleteOrigin: false,
      );
      final compressedPath = info?.path ?? picked.path;
      final durationMs = info?.duration ?? (await VideoCompress.getMediaInfo(picked.path)).duration ?? 0;
      final durationSeconds = (durationMs / 1000).round().clamp(1, 60);

      final thumb = await VideoCompress.getFileThumbnail(compressedPath, quality: 70);

      final media = await ref
          .read(productRepositoryProvider)
          .uploadVideoMedia(
            productId: _product!.id,
            videoPath: compressedPath,
            thumbnailPath: thumb.path,
            durationSeconds: durationSeconds,
          );
      setState(() => _product = _product!.copyWith(media: [..._product!.media, media]));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _mediaBusy = false);
    }
  }

  Future<void> _deleteMedia(ProductMediaItem media) async {
    if (_product == null) return;
    setState(() => _mediaBusy = true);
    try {
      await ref.read(productRepositoryProvider).deleteMedia(productId: _product!.id, mediaId: media.id);
      setState(() => _product = _product!.copyWith(media: _product!.media.where((m) => m.id != media.id).toList()));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _mediaBusy = false);
    }
  }

  void _showAddMediaSheet() {
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
                _addPhoto(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: Text(l10n.productFormAddPhotoGallery),
              onTap: () {
                Navigator.of(sheetContext).pop();
                _addPhoto(ImageSource.gallery);
              },
            ),
            ListTile(
              leading: const Icon(Icons.videocam_outlined),
              title: Text(l10n.productFormAddVideo),
              onTap: () {
                Navigator.of(sheetContext).pop();
                _addVideo();
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

    return Scaffold(
      appBar: AppBar(title: Text(_isEditing ? l10n.productFormTitleEdit : l10n.productFormTitleNew)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(SokoniDimens.space20),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    TextFormField(
                      controller: _titleController,
                      decoration: InputDecoration(labelText: l10n.productFormTitleField),
                      validator: SokoniValidators.required,
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    TextFormField(
                      controller: _descriptionController,
                      maxLines: 4,
                      decoration: InputDecoration(labelText: l10n.productFormDescription),
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    TextFormField(
                      controller: _priceController,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: l10n.productFormPrice, prefixText: 'TSh '),
                      validator: SokoniValidators.price,
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    TextFormField(
                      controller: _stockController,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: l10n.productFormStock),
                      validator: (v) {
                        final n = int.tryParse(v ?? '');
                        return n == null || n < 0 ? l10n.productFormStock : null;
                      },
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    Consumer(
                      builder: (context, ref, _) {
                        final categoriesAsync = ref.watch(categoriesProvider);
                        final locale = Localizations.localeOf(context).languageCode;
                        return categoriesAsync.when(
                          loading: () => const LinearProgressIndicator(),
                          error: (e, _) => Row(
                            children: [
                              Expanded(child: Text('$e', style: const TextStyle(color: Colors.red))),
                              TextButton(
                                onPressed: () => ref.invalidate(categoriesProvider),
                                child: Text(l10n.commonRetry),
                              ),
                            ],
                          ),
                          data: (categories) => DropdownButtonFormField<int>(
                            initialValue: _categoryId,
                            decoration: InputDecoration(labelText: l10n.productFormCategory),
                            items: [
                              for (final c in categories)
                                DropdownMenuItem(value: c.id, child: Text(c.name(locale))),
                            ],
                            onChanged: (value) => setState(() => _categoryId = value),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    SegmentedButton<String>(
                      segments: [
                        ButtonSegment(value: 'new', label: Text(l10n.productConditionNew)),
                        ButtonSegment(value: 'used', label: Text(l10n.productConditionUsed)),
                      ],
                      selected: {_condition},
                      onSelectionChanged: (selection) => setState(() => _condition = selection.first),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: SokoniDimens.space8),
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                    ],
                    const SizedBox(height: SokoniDimens.space24),
                    FilledButton(
                      onPressed: _submitting ? null : _save,
                      child: _submitting
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text(l10n.productFormSave),
                    ),
                    const SizedBox(height: SokoniDimens.space32),
                    Text(l10n.productFormMediaTitle, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: SokoniDimens.space4),
                    Text(l10n.productFormMediaHint, style: Theme.of(context).textTheme.bodySmall),
                    const SizedBox(height: SokoniDimens.space12),
                    if (_product == null)
                      Text(l10n.productFormSaveBeforeMedia, style: Theme.of(context).textTheme.bodySmall)
                    else
                      _MediaGrid(
                        media: _product!.media,
                        busy: _mediaBusy,
                        canAddMore: _product!.media.length < 8,
                        onAdd: _showAddMediaSheet,
                        onDelete: _deleteMedia,
                      ),
                  ],
                ),
              ),
            ),
    );
  }
}

class _MediaGrid extends StatelessWidget {
  const _MediaGrid({
    required this.media,
    required this.busy,
    required this.canAddMore,
    required this.onAdd,
    required this.onDelete,
  });

  final List<ProductMediaItem> media;
  final bool busy;
  final bool canAddMore;
  final VoidCallback onAdd;
  final ValueChanged<ProductMediaItem> onDelete;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: SokoniDimens.space8,
      runSpacing: SokoniDimens.space8,
      children: [
        for (final item in media)
          Stack(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                child: SokoniNetworkImage(
                  imageUrl: item.thumbPath ?? item.path,
                  width: 84,
                  height: 84,
                  fit: BoxFit.cover,
                  placeholder: (context, url) =>
                      Container(color: Theme.of(context).colorScheme.surfaceContainerHighest),
                ),
              ),
              if (item.isVideo)
                const Positioned(
                  bottom: 4,
                  left: 4,
                  child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 20),
                ),
              Positioned(
                top: -4,
                right: -4,
                child: IconButton(
                  icon: const Icon(Icons.cancel_rounded, size: 20),
                  onPressed: () => onDelete(item),
                ),
              ),
            ],
          ),
        if (canAddMore)
          InkWell(
            onTap: busy ? null : onAdd,
            borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
            child: Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                border: Border.all(color: Theme.of(context).colorScheme.outline),
                borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
              ),
              alignment: Alignment.center,
              child: busy
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.add_rounded),
            ),
          ),
      ],
    );
  }
}
