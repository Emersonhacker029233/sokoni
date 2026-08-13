import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/seller_profile.dart';
import '../../providers/seller_onboarding_providers.dart';

const _maxLicenceBytes = 5 * 1024 * 1024;

/// Step 4: business/trading licence, image or PDF, max 5MB (CLAUDE.md).
class OnboardingStep4Licence extends ConsumerStatefulWidget {
  const OnboardingStep4Licence({required this.sellerId, required this.onSubmitted, super.key});

  final int? sellerId;
  final ValueChanged<SellerProfile> onSubmitted;

  @override
  ConsumerState<OnboardingStep4Licence> createState() => _OnboardingStep4LicenceState();
}

class _OnboardingStep4LicenceState extends ConsumerState<OnboardingStep4Licence> {
  String? _filePath;
  String? _fileName;
  bool _submitting = false;
  String? _error;

  Future<void> _pickFile() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );
    final file = result?.files.single;
    if (file == null || file.path == null) return;
    if (file.size > _maxLicenceBytes) {
      setState(() => _error = AppLocalizations.of(context).onboardingLicenceHint);
      return;
    }
    setState(() {
      _filePath = file.path;
      _fileName = file.name;
      _error = null;
    });
  }

  Future<void> _submit() async {
    if (widget.sellerId == null || _filePath == null) {
      setState(() => _error = AppLocalizations.of(context).onboardingLicenceFile);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final seller = await ref.read(sellerOnboardingProvider.notifier).submitLicence(
        sellerId: widget.sellerId!,
        licenceFilePath: _filePath!,
      );
      widget.onSubmitted(seller);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  bool get _isImage {
    final name = _fileName?.toLowerCase() ?? '';
    return name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.png');
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.onboardingLicenceFile, style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: SokoniDimens.space4),
          Text(l10n.onboardingLicenceHint, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: SokoniDimens.space16),
          if (_filePath != null)
            Container(
              padding: const EdgeInsets.all(SokoniDimens.space12),
              decoration: BoxDecoration(
                border: Border.all(color: Theme.of(context).colorScheme.outline),
                borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
              ),
              child: Row(
                children: [
                  if (_isImage)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                      child: Image.file(File(_filePath!), width: 48, height: 48, fit: BoxFit.cover),
                    )
                  else
                    const Icon(Icons.picture_as_pdf_outlined, size: 40),
                  const SizedBox(width: SokoniDimens.space12),
                  Expanded(child: Text(_fileName ?? '', overflow: TextOverflow.ellipsis)),
                ],
              ),
            ),
          const SizedBox(height: SokoniDimens.space12),
          OutlinedButton.icon(
            onPressed: _pickFile,
            icon: const Icon(Icons.upload_file_outlined),
            label: Text(l10n.onboardingChooseFile),
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
                : Text(l10n.onboardingSubmit),
          ),
        ],
      ),
    );
  }
}
