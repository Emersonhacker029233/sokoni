import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/delivery.dart';
import '../../../seller/providers/seller_providers.dart';
import '../../providers/delivery_providers.dart';
import '../delivery_labels.dart';

/// A shop's return policy on the shop page and the product page
/// (docs/delivery/RETURNS.md). The server only sends the policy to users
/// inside the returns launch, so for everyone else [policyJson] is null and
/// nothing is drawn. The shop's owner can change it here.
class ReturnPolicyCard extends ConsumerWidget {
  const ReturnPolicyCard({required this.policyJson, this.ownerHandle, this.compact = false, super.key});

  final Map<String, dynamic>? policyJson;

  /// Set when the viewer owns the shop: shows "Change".
  final String? ownerHandle;

  /// One line, for the product page.
  final bool compact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final policy = ReturnPolicyInfo.fromJson(policyJson);
    if (policy == null) return const SizedBox.shrink();
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    final line = Row(
      children: [
        Icon(policy.acceptsReturns ? Icons.assignment_return_outlined : Icons.block_outlined, size: 18),
        const SizedBox(width: SokoniDimens.space8),
        Expanded(child: Text(returnPolicyLabel(l10n, policy.code), key: const Key('return-policy-label'), style: theme.textTheme.bodyMedium)),
        if (ownerHandle != null)
          TextButton(
            key: const Key('change-return-policy'),
            onPressed: () => showSokoniBottomSheet<void>(
              context: context,
              initialChildSize: 0.7,
              builder: (_) => _ReturnPolicySheet(current: policy, handle: ownerHandle!),
            ),
            child: Text(l10n.returnPolicyChange),
          ),
      ],
    );
    if (compact) return Padding(padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space4), child: line);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(SokoniDimens.space12),
      decoration: BoxDecoration(
        border: Border.all(color: theme.brightness == Brightness.dark ? SokoniColors.darkOutline : SokoniColors.outline),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          line,
          if (policy.notes != null) Text(policy.notes!, style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _ReturnPolicySheet extends ConsumerStatefulWidget {
  const _ReturnPolicySheet({required this.current, required this.handle});

  final ReturnPolicyInfo current;
  final String handle;

  @override
  ConsumerState<_ReturnPolicySheet> createState() => _ReturnPolicySheetState();
}

class _ReturnPolicySheetState extends ConsumerState<_ReturnPolicySheet> {
  late String _code = widget.current.code;
  late final _notes = TextEditingController(text: widget.current.notes ?? '');
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(deliveryRepositoryProvider).updateReturnPolicy(policy: _code, notes: _notes.text.trim().isEmpty ? null : _notes.text.trim());
      ref.invalidate(sellerProfileProvider(widget.handle));
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) setState(() => _error = deliveryErrorMessage(AppLocalizations.of(context), e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.all(SokoniDimens.space16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.returnPolicyTitle, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: SokoniDimens.space4),
          Text(l10n.returnPolicyExplainer, style: theme.textTheme.bodySmall),
          RadioGroup<String>(
            groupValue: _code,
            onChanged: (value) => setState(() => _code = value ?? _code),
            child: Column(
              children: [
                for (final code in const ['none', '24h', '3d', '7d'])
                  RadioListTile<String>(key: Key('return-policy-$code'), value: code, title: Text(returnPolicyLabel(l10n, code))),
              ],
            ),
          ),
          TextField(
            controller: _notes,
            maxLength: 500,
            maxLines: 2,
            decoration: InputDecoration(labelText: l10n.returnPolicyNotesLabel, hintText: l10n.returnPolicyNotesHint),
          ),
          if (_error != null) Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          const SizedBox(height: SokoniDimens.space8),
          FilledButton(key: const Key('save-return-policy'), onPressed: _saving ? null : _save, child: Text(l10n.returnPolicySave)),
        ],
      ),
    );
  }
}
