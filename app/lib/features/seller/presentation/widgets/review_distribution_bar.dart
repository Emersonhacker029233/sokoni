import 'package:flutter/material.dart';

import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';

/// Rating distribution bar on the shop profile (CLAUDE.md feature 2:
/// "average, count, and a distribution bar").
class ReviewDistributionBar extends StatelessWidget {
  const ReviewDistributionBar({required this.distribution, required this.total, super.key});

  final Map<String, int> distribution;
  final int total;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (var star = 5; star >= 1; star--) _StarRow(star: star, count: distribution['$star'] ?? 0, total: total),
      ],
    );
  }
}

class _StarRow extends StatelessWidget {
  const _StarRow({required this.star, required this.count, required this.total});

  final int star;
  final int count;
  final int total;

  @override
  Widget build(BuildContext context) {
    final fraction = total == 0 ? 0.0 : count / total;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Text('$star', style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(width: 4),
          const Icon(Icons.star_rounded, size: 12, color: SokoniColors.sokoniYellow),
          const SizedBox(width: SokoniDimens.space8),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: fraction,
                minHeight: 6,
                backgroundColor: SokoniColors.surfaceAlt,
                color: SokoniColors.sokoniYellow,
              ),
            ),
          ),
          const SizedBox(width: SokoniDimens.space8),
          SizedBox(
            width: 24,
            child: Text('$count', style: Theme.of(context).textTheme.bodySmall),
          ),
        ],
      ),
    );
  }
}
