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
import '../../../../core/utils/vehicle_makes.dart';
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
  /// The top-level category — always set once a category is picked at all.
  int? _parentCategoryId;

  /// The optional subcategory within [_parentCategoryId]; null means "use
  /// the parent category itself" (CLAUDE.md restructure: subcategories are
  /// optional, defaulting to the parent). The actual value sent to the API
  /// is whichever of the two is more specific — see [_effectiveCategoryId].
  int? _subcategoryId;

  String _condition = 'new';

  /// C3 (tester feedback): Cars' Make/Model attributes — not a third
  /// category level, so these live alongside the other plain fields
  /// rather than as a third dependent-dropdown tier.
  String? _make;
  String? _model;
  int? _year;
  bool _isCarsSelected = false;

  Product? _product;
  bool _loading = false;
  bool _submitting = false;
  bool _mediaBusy = false;
  String? _error;

  bool get _isEditing => widget.productId != null;

  int? get _effectiveCategoryId => _subcategoryId ?? _parentCategoryId;

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
        // A saved product's category may itself be a subcategory (its
        // parentId set) or a top-level one — split back into "which
        // parent" + "which subcategory, if any" for the two pickers below.
        final category = product.category;
        _parentCategoryId = category?.parentId ?? category?.id;
        _subcategoryId = category?.parentId != null ? category?.id : null;
        _isCarsSelected = category?.isCars ?? false;
        _make = product.attributes?['make'];
        _model = product.attributes?['model'];
        _year = int.tryParse(product.attributes?['year'] ?? '');
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false) || _effectiveCategoryId == null) return;
    if (_isCarsSelected && (_make == null || _model == null)) {
      setState(() => _error = AppLocalizations.of(context).productFormMakeModelRequired);
      return;
    }
    if (_isCarsSelected && _year == null) {
      setState(() => _error = AppLocalizations.of(context).productFormYearRequired);
      return;
    }
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
          categoryId: _effectiveCategoryId!,
          title: _titleController.text.trim(),
          description: _descriptionController.text.trim().isEmpty ? null : _descriptionController.text.trim(),
          price: price,
          stock: stock,
          condition: _condition,
          make: _isCarsSelected ? _make : null,
          model: _isCarsSelected ? _model : null,
          year: _isCarsSelected ? _year?.toString() : null,
        );
      } else {
        saved = await repo.updateProduct(
          productId: _product!.id,
          categoryId: _effectiveCategoryId,
          title: _titleController.text.trim(),
          description: _descriptionController.text.trim(),
          price: price,
          stock: stock,
          condition: _condition,
          make: _isCarsSelected ? _make : null,
          model: _isCarsSelected ? _model : null,
          year: _isCarsSelected ? _year?.toString() : null,
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
                        final categoriesAsync = ref.watch(topLevelCategoriesProvider);
                        final locale = Localizations.localeOf(context).languageCode;
                        return categoriesAsync.when(
                          loading: () => const LinearProgressIndicator(),
                          error: (e, _) => Row(
                            children: [
                              Expanded(child: Text('$e', style: const TextStyle(color: Colors.red))),
                              TextButton(
                                onPressed: () => ref.invalidate(topLevelCategoriesProvider),
                                child: Text(l10n.commonRetry),
                              ),
                            ],
                          ),
                          data: (categories) => DropdownButtonFormField<int>(
                            initialValue: _parentCategoryId,
                            decoration: InputDecoration(labelText: l10n.productFormCategory),
                            items: [
                              for (final c in categories)
                                DropdownMenuItem(value: c.id, child: Text(c.name(locale))),
                            ],
                            onChanged: (value) => setState(() {
                              _parentCategoryId = value;
                              // A subcategory picked under the old parent
                              // never carries over silently to a new one.
                              _subcategoryId = null;
                              // Cars is always a subcategory, never a
                              // top-level pick — changing the parent can
                              // only ever leave Cars.
                              _isCarsSelected = false;
                              _make = null;
                              _model = null;
                              _year = null;
                            }),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: SokoniDimens.space16),
                    if (_parentCategoryId != null)
                      Consumer(
                        builder: (context, ref, _) {
                          final subcategoriesAsync = ref.watch(subcategoriesProvider(_parentCategoryId!));
                          final locale = Localizations.localeOf(context).languageCode;
                          return subcategoriesAsync.when(
                            loading: () => const LinearProgressIndicator(),
                            error: (e, _) => const SizedBox.shrink(),
                            data: (subcategories) {
                              if (subcategories.isEmpty) return const SizedBox.shrink();
                              return Padding(
                                padding: const EdgeInsets.only(bottom: SokoniDimens.space16),
                                child: DropdownButtonFormField<int?>(
                                  initialValue: _subcategoryId,
                                  decoration: InputDecoration(labelText: l10n.productFormSubcategory),
                                  items: [
                                    DropdownMenuItem(value: null, child: Text(l10n.productFormSubcategoryNone)),
                                    for (final c in subcategories)
                                      DropdownMenuItem(value: c.id, child: Text(c.name(locale))),
                                  ],
                                  onChanged: (value) => setState(() {
                                    _subcategoryId = value;
                                    final selected = subcategories.where((c) => c.id == value).firstOrNull;
                                    _isCarsSelected = selected?.isCars ?? false;
                                    if (!_isCarsSelected) {
                                      _make = null;
                                      _model = null;
                                      _year = null;
                                    }
                                  }),
                                ),
                              );
                            },
                          );
                        },
                      ),
                    // C3 (tester feedback): Make/Model are attributes of a
                    // Cars listing, not a third category level — two
                    // dependent dropdowns, shown and required only when
                    // Cars is the selected (sub)category.
                    if (_isCarsSelected) ...[
                      DropdownButtonFormField<String>(
                        initialValue: _make,
                        decoration: InputDecoration(labelText: l10n.productFormMake),
                        items: [
                          for (final make in VehicleMakes.makes)
                            DropdownMenuItem(value: make, child: Text(make)),
                        ],
                        onChanged: (value) => setState(() {
                          _make = value;
                          _model = null;
                          _year = null;
                        }),
                        validator: (value) => value == null ? l10n.productFormMakeModelRequired : null,
                      ),
                      const SizedBox(height: SokoniDimens.space16),
                      if (_make != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: SokoniDimens.space16),
                          child: DropdownButtonFormField<String>(
                            initialValue: _model,
                            decoration: InputDecoration(labelText: l10n.productFormModel),
                            items: [
                              for (final model in VehicleMakes.modelsFor(_make))
                                DropdownMenuItem(value: model, child: Text(model)),
                            ],
                            onChanged: (value) => setState(() {
                              _model = value;
                              _year = null;
                            }),
                            validator: (value) => value == null ? l10n.productFormMakeModelRequired : null,
                          ),
                        ),
                      // C4 (tester feedback): Year, the third dependent
                      // step — revealed once a model is picked, though its
                      // own options are a flat 1990-current range rather
                      // than one narrowed by the model (see DECISIONS.md).
                      if (_model != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: SokoniDimens.space16),
                          child: DropdownButtonFormField<int>(
                            initialValue: _year,
                            decoration: InputDecoration(labelText: l10n.productFormYear),
                            items: [
                              for (final year in VehicleMakes.years)
                                DropdownMenuItem(value: year, child: Text(year.toString())),
                            ],
                            onChanged: (value) => setState(() => _year = value),
                            validator: (value) => value == null ? l10n.productFormYearRequired : null,
                          ),
                        ),
                    ],
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
