import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/motion/sokoni_bottom_sheet.dart';
import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme/dimens.dart';
import '../../features/auth/presentation/sign_in_prompt_sheet.dart';
import '../../features/moderation/providers/block_providers.dart';

/// Report flow for every kind of user content (CLAUDE.md feature 11;
/// Apple Guideline 1.2: "a mechanism for users to flag objectionable
/// content"): reason picker → the moderation queue, which alerts the
/// moderators at once. [reportableType] is one of the API's report types:
/// product, shop, message, comment, review, showcase, update, offer, user.
/// A guest is asked to sign in first.
Future<void> showReportSheet(
  BuildContext context,
  WidgetRef ref, {
  required String reportableType,
  required int reportableId,
}) {
  if (!ref.read(authStateProvider).isAuthenticated) {
    return showSignInPrompt(context, message: AppLocalizations.of(context).guestPromptReport);
  }

  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.75,
    builder: (context) => _ReportSheetContent(reportableType: reportableType, reportableId: reportableId),
  );
}

/// The reasons offered, as the codes the server stores (`Report::REASONS`)
/// with their translated labels. The first four are the ones Apple names.
List<(String, String)> _reasons(AppLocalizations l10n) => [
  ('abusive', l10n.reportReasonAbusive),
  ('harassment', l10n.reportReasonHarassment),
  ('sexual_violent', l10n.reportReasonSexualViolent),
  ('scam', l10n.reportReasonScam),
  ('prohibited', l10n.reportReasonProhibited),
  ('misleading', l10n.reportReasonMisleading),
  ('not_business', l10n.reportReasonNotBusiness),
  ('spam', l10n.reportReasonSpam),
  ('other', l10n.reportReasonOther),
];

class _ReportSheetContent extends ConsumerStatefulWidget {
  const _ReportSheetContent({required this.reportableType, required this.reportableId});

  final String reportableType;
  final int reportableId;

  @override
  ConsumerState<_ReportSheetContent> createState() => _ReportSheetContentState();
}

class _ReportSheetContentState extends ConsumerState<_ReportSheetContent> {
  final _note = TextEditingController();
  bool _submitting = false;
  bool _writingNote = false;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit(String reason) async {
    setState(() => _submitting = true);
    final l10n = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(moderationRepositoryProvider).report(
        type: widget.reportableType,
        id: widget.reportableId,
        reason: reason,
        note: _note.text,
      );
      if (mounted) Navigator.of(context).pop();
      messenger.showSnackBar(SnackBar(content: Text(l10n.reportSubmitted)));
    } catch (e) {
      final message = e is ApiException ? e.message : l10n.feedErrorBody;
      messenger.showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.reportTitle, style: textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space4),
        Text(l10n.reportSubtitle, style: textTheme.bodySmall),
        const SizedBox(height: SokoniDimens.space12),
        if (_submitting)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: SokoniDimens.space24),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_writingNote) ...[
          TextField(
            controller: _note,
            autofocus: true,
            maxLength: 1000,
            minLines: 3,
            maxLines: 6,
            decoration: InputDecoration(hintText: l10n.reportNoteHint),
          ),
          const SizedBox(height: SokoniDimens.space8),
          SizedBox(
            width: double.infinity,
            child: FilledButton(onPressed: () => _submit('other'), child: Text(l10n.reportSend)),
          ),
        ] else
          for (final (code, label) in _reasons(l10n))
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(label),
              trailing: code == 'other' ? const Icon(Icons.chevron_right_rounded) : null,
              onTap: () => code == 'other' ? setState(() => _writingNote = true) : _submit(code),
            ),
      ],
    );
  }
}
