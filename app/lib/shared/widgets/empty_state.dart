import 'package:flutter/material.dart';

import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';

/// Every empty state gets an illustration, one sentence, one action — no
/// blank screens ever (CLAUDE.md feature 10).
class SokoniEmptyState extends StatelessWidget {
  const SokoniEmptyState({
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
    super.key,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(SokoniDimens.space32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(
                // Yellow as the fill, black icon on top — not yellow icon
                // on surfaceAlt, which in light mode is functionally
                // yellow-on-white (~1.6:1, fails even the 3:1 a graphic
                // needs). Same pattern as onYellow everywhere else in the
                // app. See DECISIONS.md.
                color: SokoniColors.sokoniYellow,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Icon(icon, size: 32, color: SokoniColors.onYellow),
            ),
            const SizedBox(height: SokoniDimens.space20),
            Text(title, style: textTheme.titleMedium, textAlign: TextAlign.center),
            const SizedBox(height: SokoniDimens.space8),
            Text(
              message,
              style: textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: SokoniDimens.space20),
              OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}
