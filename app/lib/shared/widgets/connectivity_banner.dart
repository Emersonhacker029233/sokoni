import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';

/// "Showing saved results" banner (CLAUDE.md feature 10) — wrap a screen's
/// body in this and it slides a banner in above the content whenever
/// [isOnlineProvider] goes false, without the caller needing to know
/// anything about connectivity itself.
class ConnectivityBanner extends ConsumerWidget {
  const ConnectivityBanner({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isOnline = ref.watch(isOnlineProvider);
    final l10n = AppLocalizations.of(context);

    return Column(
      children: [
        AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          child: isOnline
              ? const SizedBox(width: double.infinity)
              : Container(
                  width: double.infinity,
                  color: SokoniColors.sokoniBlack,
                  padding: const EdgeInsets.symmetric(
                    horizontal: SokoniDimens.space16,
                    vertical: SokoniDimens.space8,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.cloud_off_rounded, size: 14, color: SokoniColors.sokoniYellow),
                      const SizedBox(width: SokoniDimens.space8),
                      Expanded(
                        child: Text(
                          l10n.offlineShowingSavedResults,
                          style: const TextStyle(color: Colors.white, fontSize: 12),
                        ),
                      ),
                    ],
                  ),
                ),
        ),
        Expanded(child: child),
      ],
    );
  }
}
