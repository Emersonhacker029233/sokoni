import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../providers/delivery_providers.dart';
import 'delivery_labels.dart';

/// "Request a return" (docs/delivery/RETURNS.md): a reason, 1 to 3 photos
/// and an optional comment, sent to the shop. The server checks the
/// window, the policy and blocks; this screen only collects the request.
class RequestReturnScreen extends ConsumerStatefulWidget {
  const RequestReturnScreen({required this.orderId, super.key});

  final int orderId;

  @override
  ConsumerState<RequestReturnScreen> createState() => _RequestReturnScreenState();
}

class _RequestReturnScreenState extends ConsumerState<RequestReturnScreen> {
  static const _reasons = ['changed_mind', 'wrong_item', 'damaged', 'not_as_described'];
  static const _maxPhotos = 3;

  final _comment = TextEditingController();
  final List<String> _photos = [];
  String? _reason;
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _addPhoto(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 80, maxWidth: 1600);
    if (picked != null && mounted) setState(() => _photos.add(picked.path));
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context);
    if (_reason == null || _photos.isEmpty) {
      setState(() => _error = l10n.returnFormIncomplete);
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await ref.read(deliveryRepositoryProvider).requestReturn(
        widget.orderId,
        reason: _reason!,
        comment: _comment.text.trim().isEmpty ? null : _comment.text.trim(),
        photos: _photos,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.returnSent)));
      context.pop();
    } catch (e) {
      if (mounted) setState(() => _error = deliveryErrorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.returnRequestButton)),
      body: ListView(
        padding: const EdgeInsets.all(SokoniDimens.space16),
        children: [
          Text(l10n.returnReasonQuestion, style: theme.textTheme.titleSmall),
          RadioGroup<String>(
            groupValue: _reason,
            onChanged: (value) => setState(() => _reason = value),
            child: Column(
              children: [
                for (final reason in _reasons)
                  RadioListTile<String>(
                    key: Key('return-reason-$reason'),
                    value: reason,
                    title: Text(returnReasonLabel(l10n, reason)),
                    subtitle: Text(reason == 'changed_mind' ? l10n.returnYouPayRide : l10n.returnShopPaysRide, style: theme.textTheme.bodySmall),
                  ),
              ],
            ),
          ),
          const SizedBox(height: SokoniDimens.space16),
          Text(l10n.returnPhotosTitle, style: theme.textTheme.titleSmall),
          const SizedBox(height: SokoniDimens.space8),
          Wrap(
            spacing: SokoniDimens.space8,
            runSpacing: SokoniDimens.space8,
            children: [
              for (var i = 0; i < _photos.length; i++)
                Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.file(File(_photos[i]), width: 88, height: 88, fit: BoxFit.cover, semanticLabel: l10n.returnPhotoLabel(i + 1)),
                    ),
                    Positioned(
                      right: 0,
                      top: 0,
                      child: IconButton.filledTonal(
                        tooltip: l10n.returnRemovePhoto,
                        iconSize: 18,
                        onPressed: () => setState(() => _photos.removeAt(i)),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ),
                  ],
                ),
              if (_photos.length < _maxPhotos) ...[
                _AddPhoto(icon: Icons.photo_camera_outlined, label: l10n.returnTakePhoto, onTap: () => _addPhoto(ImageSource.camera)),
                _AddPhoto(icon: Icons.photo_library_outlined, label: l10n.returnChoosePhoto, onTap: () => _addPhoto(ImageSource.gallery)),
              ],
            ],
          ),
          const SizedBox(height: SokoniDimens.space16),
          TextField(
            key: const Key('return-comment'),
            controller: _comment,
            maxLength: 1000,
            maxLines: 4,
            decoration: InputDecoration(labelText: l10n.returnCommentLabel),
          ),
          Text(l10n.returnBusinessContentNote, style: theme.textTheme.bodySmall),
          if (_error != null) ...[
            const SizedBox(height: SokoniDimens.space8),
            Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          ],
          const SizedBox(height: SokoniDimens.space16),
          FilledButton(
            key: const Key('send-return'),
            onPressed: _sending ? null : _submit,
            child: _sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Text(l10n.returnSend),
          ),
        ],
      ),
    );
  }
}

class _AddPhoto extends StatelessWidget {
  const _AddPhoto({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: label,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          width: 88,
          height: 88,
          decoration: BoxDecoration(border: Border.all(color: Theme.of(context).dividerColor), borderRadius: BorderRadius.circular(12)),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [Icon(icon), Text(label, textAlign: TextAlign.center, style: Theme.of(context).textTheme.labelSmall)],
          ),
        ),
      ),
    );
  }
}
