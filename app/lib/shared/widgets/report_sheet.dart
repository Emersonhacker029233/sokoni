import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/motion/sokoni_bottom_sheet.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/dimens.dart';
import '../../data/api/report_api.dart';

/// Report flow used on every product, shop and message (CLAUDE.md feature
/// 11): reason picker → submitted to the moderation queue.
Future<void> showReportSheet(
  BuildContext context,
  WidgetRef ref, {
  required String reportableType,
  required int reportableId,
}) {
  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.4,
    builder: (context) => _ReportSheetContent(reportableType: reportableType, reportableId: reportableId),
  );
}

class _ReportSheetContent extends ConsumerStatefulWidget {
  const _ReportSheetContent({required this.reportableType, required this.reportableId});

  final String reportableType;
  final int reportableId;

  @override
  ConsumerState<_ReportSheetContent> createState() => _ReportSheetContentState();
}

class _ReportSheetContentState extends ConsumerState<_ReportSheetContent> {
  bool _submitting = false;

  Future<void> _submit(String reason) async {
    setState(() => _submitting = true);
    final l10n = AppLocalizations.of(context);
    try {
      await ref.read(reportApiProvider).submit({
        'reportable_type': widget.reportableType,
        'reportable_id': widget.reportableId,
        'reason': reason,
      });
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.reportSubmitted)));
      }
    } catch (e) {
      if (mounted) {
        final message = e is ApiException ? e.message : l10n.feedErrorBody;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final reasons = [
      l10n.reportReasonNotBusiness,
      l10n.reportReasonProhibited,
      l10n.reportReasonMisleading,
      l10n.reportReasonSpam,
    ];

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.reportTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        if (_submitting)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: SokoniDimens.space24),
            child: Center(child: CircularProgressIndicator()),
          )
        else
          for (final reason in reasons)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(reason),
              onTap: () => _submit(reason),
            ),
      ],
    );
  }
}
