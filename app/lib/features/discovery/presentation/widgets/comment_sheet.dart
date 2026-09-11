import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/providers.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/comment.dart';
import '../../../auth/presentation/auth_entry_sheet.dart';
import '../../providers/feed_providers.dart';
import '../../../../shared/widgets/sokoni_avatar.dart';

/// Comment sheet (CLAUDE.md Part 3) — opened from a feed card's comment
/// icon or its "N comments" line. Built directly on `showModalBottomSheet`/
/// `DraggableScrollableSheet` rather than the shared `showSokoniBottomSheet`
/// primitive: a comment thread needs its own independently-scrolling list
/// *and* a composer row pinned below it, which that primitive's single
/// scrollable content slot can't express — the drag handle, radius and
/// colour chrome below still match it exactly, so this reads as the same
/// visual language, not a new motion primitive.
Future<void> showCommentSheet(BuildContext context, {required int productId}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    barrierColor: Colors.black.withValues(alpha: 0.4),
    builder: (context) => _CommentSheetBody(productId: productId),
  );
}

class _CommentSheetBody extends ConsumerStatefulWidget {
  const _CommentSheetBody({required this.productId});

  final int productId;

  @override
  ConsumerState<_CommentSheetBody> createState() => _CommentSheetBodyState();
}

class _CommentSheetBodyState extends ConsumerState<_CommentSheetBody> {
  final _bodyController = TextEditingController();
  int? _replyingToId;
  String? _replyingToName;
  bool _posting = false;
  String? _error;

  @override
  void dispose() {
    _bodyController.dispose();
    super.dispose();
  }

  void _startReply(ProductComment comment) {
    setState(() {
      _replyingToId = comment.id;
      _replyingToName = comment.user.name;
    });
  }

  Future<void> _post() async {
    final body = _bodyController.text.trim();
    if (body.isEmpty) return;

    if (!ref.read(authStateProvider).isAuthenticated) {
      await showAuthEntrySheet(context);
      if (!ref.read(authStateProvider).isAuthenticated) return;
    }

    setState(() {
      _posting = true;
      _error = null;
    });
    try {
      await ref
          .read(commentThreadProvider(widget.productId).notifier)
          .post(body: body, parentId: _replyingToId);
      _bodyController.clear();
      setState(() {
        _replyingToId = null;
        _replyingToName = null;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _posting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final threadAsync = ref.watch(commentThreadProvider(widget.productId));

    return DraggableScrollableSheet(
      initialChildSize: 0.7,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Container(
          decoration: BoxDecoration(
            color: isDark ? SokoniColors.darkSurface : SokoniColors.surface,
            borderRadius: const BorderRadius.vertical(
              top: Radius.circular(SokoniDimens.radiusSheet),
            ),
          ),
          child: Column(
            children: [
              const SizedBox(height: SokoniDimens.space12),
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: isDark
                      ? SokoniColors.darkOutline
                      : SokoniColors.outline,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(
                  vertical: SokoniDimens.space12,
                ),
                child: Text(
                  l10n.commentSheetTitle,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: threadAsync.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (error, _) => Center(
                    child: Text(
                      error is ApiException
                          ? error.message
                          : l10n.feedErrorBody,
                    ),
                  ),
                  data: (state) => state.items.isEmpty
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(SokoniDimens.space24),
                            child: Text(
                              l10n.commentEmptyBody,
                              textAlign: TextAlign.center,
                            ),
                          ),
                        )
                      : ListView.builder(
                          controller: scrollController,
                          padding: const EdgeInsets.symmetric(
                            horizontal: SokoniDimens.space16,
                            vertical: SokoniDimens.space8,
                          ),
                          itemCount:
                              state.items.length + (state.hasMore ? 1 : 0),
                          itemBuilder: (context, index) {
                            if (index >= state.items.length) {
                              return Center(
                                child: TextButton(
                                  onPressed: () => ref
                                      .read(
                                        commentThreadProvider(
                                          widget.productId,
                                        ).notifier,
                                      )
                                      .loadMore(),
                                  child: Text(l10n.commonLoadMore),
                                ),
                              );
                            }
                            final comment = state.items[index];
                            return _CommentTile(
                              comment: comment,
                              onReply: () => _startReply(comment),
                            );
                          },
                        ),
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.all(SokoniDimens.space12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_replyingToName != null)
                        Padding(
                          padding: const EdgeInsets.only(
                            bottom: SokoniDimens.space4,
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  l10n.commentReplyingTo(_replyingToName!),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ),
                              GestureDetector(
                                onTap: () => setState(() {
                                  _replyingToId = null;
                                  _replyingToName = null;
                                }),
                                child: const Icon(
                                  Icons.close_rounded,
                                  size: 16,
                                ),
                              ),
                            ],
                          ),
                        ),
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(
                            bottom: SokoniDimens.space4,
                          ),
                          child: Text(
                            _error!,
                            style: const TextStyle(
                              color: SokoniColors.danger,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _bodyController,
                              minLines: 1,
                              maxLines: 4,
                              decoration: InputDecoration(
                                hintText: l10n.commentInputHint,
                              ),
                            ),
                          ),
                          const SizedBox(width: SokoniDimens.space8),
                          IconButton(
                            constraints: const BoxConstraints(
                              minWidth: 44,
                              minHeight: 44,
                            ),
                            icon: _posting
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.send_rounded),
                            onPressed: _posting ? null : _post,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _CommentTile extends StatelessWidget {
  const _CommentTile({required this.comment, required this.onReply});

  final ProductComment comment;
  final VoidCallback onReply;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _CommentRow(comment: comment, onReply: onReply),
          for (final reply in comment.replies)
            Padding(
              padding: const EdgeInsets.only(
                left: SokoniDimens.space32,
                top: SokoniDimens.space8,
              ),
              child: _CommentRow(comment: reply, onReply: null),
            ),
        ],
      ),
    );
  }
}

class _CommentRow extends StatelessWidget {
  const _CommentRow({required this.comment, required this.onReply});

  final ProductComment comment;
  final VoidCallback? onReply;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SokoniAvatar(
          imageUrl: comment.user.avatar,
          radius: 14,
          backgroundColor: SokoniColors.surfaceAlt,
          fallbackIcon: Icons.person_outline,
          fallbackIconSize: 14,
        ),
        const SizedBox(width: SokoniDimens.space8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    comment.user.name,
                    style: textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (comment.isFromSeller) ...[
                    const SizedBox(width: SokoniDimens.space4),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 6,
                        vertical: 1,
                      ),
                      decoration: BoxDecoration(
                        color: SokoniColors.sokoniYellow,
                        borderRadius: BorderRadius.circular(
                          SokoniDimens.radiusChip,
                        ),
                      ),
                      child: Text(
                        l10n.commentSellerBadge,
                        style: const TextStyle(
                          color: SokoniColors.onYellow,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              Text(comment.body, style: textTheme.bodyMedium),
              if (onReply != null)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: GestureDetector(
                    onTap: onReply,
                    child: Text(
                      l10n.commentReplyAction,
                      style: textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}
