import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/motion.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/seller_profile.dart';
import '../../providers/seller_providers.dart';

/// Owner's "Edit profile" action (CLAUDE.md Part 4) — shop logo, bio and
/// WhatsApp visibility. Shop name/handle/category are set once at
/// onboarding with no edit surface anywhere in this app, so this sheet
/// doesn't add one either — editing an identity field a buyer may already
/// be following/searching by is a bigger product decision than this brief
/// asks for.
Future<void> showEditShopProfileSheet(BuildContext context, WidgetRef ref, SellerProfile seller) {
  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.6,
    builder: (context) => _EditShopProfileForm(seller: seller),
  );
}

class _EditShopProfileForm extends ConsumerStatefulWidget {
  const _EditShopProfileForm({required this.seller});

  final SellerProfile seller;

  @override
  ConsumerState<_EditShopProfileForm> createState() => _EditShopProfileFormState();
}

class _EditShopProfileFormState extends ConsumerState<_EditShopProfileForm> {
  late final TextEditingController _bioController;
  late final TextEditingController _whatsappController;
  late bool _showWhatsapp;
  bool _submitting = false;
  bool _uploadingLogo = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bioController = TextEditingController(text: widget.seller.bio ?? '');
    _whatsappController = TextEditingController(text: widget.seller.whatsapp ?? '');
    _showWhatsapp = widget.seller.whatsapp != null;
  }

  @override
  void dispose() {
    _bioController.dispose();
    _whatsappController.dispose();
    super.dispose();
  }

  Future<void> _pickLogo() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 90);
    if (picked == null) return;

    setState(() => _uploadingLogo = true);
    try {
      final targetDir = await getTemporaryDirectory();
      final targetPath = p.join(targetDir.path, 'shop_logo_${DateTime.now().millisecondsSinceEpoch}.jpg');
      final compressed = await FlutterImageCompress.compressAndGetFile(
        picked.path,
        targetPath,
        quality: 85,
        minWidth: 512,
        minHeight: 512,
      );
      await ref
          .read(sellerRepositoryProvider)
          .uploadLogo(sellerId: widget.seller.id, logoPath: compressed?.path ?? picked.path);
      ref.invalidate(sellerProfileProvider(widget.seller.handle));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  Future<void> _save() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref.read(sellerRepositoryProvider).updateProfile(
        sellerId: widget.seller.id,
        bio: _bioController.text.trim(),
        whatsapp: _showWhatsapp && _whatsappController.text.trim().isNotEmpty
            ? _whatsappController.text.trim()
            : null,
        showWhatsapp: _showWhatsapp,
      );
      ref.invalidate(sellerProfileProvider(widget.seller.handle));
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.editProfileTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        Center(
          child: GestureDetector(
            onTap: _uploadingLogo ? null : _pickLogo,
            child: Stack(
              alignment: Alignment.center,
              children: [
                CircleAvatar(
                  radius: 40,
                  backgroundImage: widget.seller.logo != null ? NetworkImage(widget.seller.logo!) : null,
                  child: widget.seller.logo == null ? const Icon(Icons.storefront_outlined, size: 32) : null,
                ),
                if (_uploadingLogo)
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
        TextField(
          controller: _bioController,
          maxLines: 3,
          maxLength: 1000,
          decoration: InputDecoration(labelText: l10n.editProfileBioLabel),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _showWhatsapp,
          onChanged: (value) => setState(() => _showWhatsapp = value),
          title: Text(l10n.editProfileShowWhatsapp),
        ),
        if (_showWhatsapp)
          TextField(
            controller: _whatsappController,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(
              labelText: l10n.editProfileWhatsappLabel,
              hintText: '+2557XXXXXXXX',
            ),
          ),
        if (_error != null) ...[
          const SizedBox(height: SokoniDimens.space8),
          Text(_error!, style: const TextStyle(color: Colors.red)),
        ],
        const SizedBox(height: SokoniDimens.space20),
        FilledButton(
          onPressed: _submitting ? null : _save,
          child: _submitting
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(l10n.editProfileSave),
        ),
      ],
    );
  }
}
