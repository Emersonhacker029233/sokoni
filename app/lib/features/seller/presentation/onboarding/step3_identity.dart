import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/validators.dart';
import '../../../../data/models/seller_profile.dart';
import '../../providers/seller_onboarding_providers.dart';

/// Step 3: NIDA number (CLAUDE.md — 20 digits, format-validated only; live
/// verification is out of scope, see NidaVerifier / BLOCKERS.md) + an ID
/// photo, compressed client-side before upload.
class OnboardingStep3Identity extends ConsumerStatefulWidget {
  const OnboardingStep3Identity({required this.sellerId, required this.onSubmitted, super.key});

  final int? sellerId;
  final ValueChanged<SellerProfile> onSubmitted;

  @override
  ConsumerState<OnboardingStep3Identity> createState() => _OnboardingStep3IdentityState();
}

class _OnboardingStep3IdentityState extends ConsumerState<OnboardingStep3Identity> {
  final _formKey = GlobalKey<FormState>();
  final _nidaController = TextEditingController();
  String? _photoPath;
  bool _submitting = false;
  bool _compressing = false;
  String? _error;

  @override
  void dispose() {
    _nidaController.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 90);
    if (picked == null) return;
    setState(() => _compressing = true);
    try {
      final targetDir = await getTemporaryDirectory();
      final targetPath = p.join(
        targetDir.path,
        'nida_${DateTime.now().millisecondsSinceEpoch}.jpg',
      );
      final result = await FlutterImageCompress.compressAndGetFile(
        picked.path,
        targetPath,
        quality: 80,
        minWidth: 1280,
        minHeight: 1280,
      );
      setState(() => _photoPath = result?.path ?? picked.path);
    } finally {
      if (mounted) setState(() => _compressing = false);
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || widget.sellerId == null) return;
    if (_photoPath == null) {
      setState(() => _error = AppLocalizations.of(context).onboardingNidaPhoto);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final seller = await ref.read(sellerOnboardingProvider.notifier).submitIdentity(
        sellerId: widget.sellerId!,
        nidaNumber: _nidaController.text.trim(),
        nidaImagePath: _photoPath!,
      );
      widget.onSubmitted(seller);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextFormField(
              controller: _nidaController,
              keyboardType: TextInputType.number,
              maxLength: 20,
              decoration: InputDecoration(labelText: l10n.onboardingNidaNumber),
              validator: SokoniValidators.nidaNumber,
            ),
            const SizedBox(height: SokoniDimens.space16),
            Text(l10n.onboardingNidaPhoto, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: SokoniDimens.space8),
            if (_compressing)
              const Center(child: CircularProgressIndicator())
            else if (_photoPath != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
                child: Image.file(File(_photoPath!), height: 180, width: double.infinity, fit: BoxFit.cover),
              ),
            const SizedBox(height: SokoniDimens.space8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _compressing ? null : () => _pickPhoto(ImageSource.camera),
                    icon: const Icon(Icons.camera_alt_outlined),
                    label: Text(l10n.onboardingTakePhoto),
                  ),
                ),
                const SizedBox(width: SokoniDimens.space12),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _compressing ? null : () => _pickPhoto(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library_outlined),
                    label: Text(l10n.onboardingChooseFromGallery),
                  ),
                ),
              ],
            ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: SokoniDimens.space24),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.onboardingNext),
            ),
          ],
        ),
      ),
    );
  }
}
