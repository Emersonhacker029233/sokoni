import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/dimens.dart';

/// Motion primitive 7 — bottom sheets.
///
/// A draggable, physics-based modal bottom sheet with a rubber-band
/// overscroll feel (via [BouncingScrollPhysics] on its internal scroll
/// view) and a drag handle. Used for filters, seller quick-view, and order
/// confirmation throughout the app.
Future<T?> showSokoniBottomSheet<T>({
  required BuildContext context,
  required WidgetBuilder builder,
  double initialChildSize = 0.5,
  double minChildSize = 0.25,
  double maxChildSize = 0.92,
  bool isScrollControlled = true,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: isScrollControlled,
    backgroundColor: Colors.transparent,
    barrierColor: Colors.black.withValues(alpha: 0.4),
    builder: (context) {
      return DraggableScrollableSheet(
        initialChildSize: initialChildSize,
        minChildSize: minChildSize,
        maxChildSize: maxChildSize,
        expand: false,
        snap: true,
        builder: (context, scrollController) {
          final isDark = Theme.of(context).brightness == Brightness.dark;
          return Container(
            decoration: BoxDecoration(
              color: isDark ? SokoniColors.darkSurface : SokoniColors.surface,
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(SokoniDimens.radiusSheet),
              ),
            ),
            // D1 (tester feedback): this is the one shared primitive nearly
            // every sheet in the app is built from (filters, seller
            // quick-view, order confirmation, ...) — it had no notion of
            // the device's own bottom inset at all, so a gesture-nav
            // device's home-indicator strip could sit right on top of a
            // sheet's last button/field. A handful of individual sheets
            // had worked around this themselves with their own SafeArea;
            // fixing it here means every sheet gets it for free, and a
            // call site's own SafeArea (harmless — MediaQuery reports zero
            // padding left to consume by the time it gets there) no
            // longer has to remember to.
            child: SafeArea(
              top: false,
              child: Column(
                children: [
                  const SizedBox(height: SokoniDimens.space12),
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: isDark ? SokoniColors.darkOutline : SokoniColors.outline,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  Expanded(
                    child: SingleChildScrollView(
                      controller: scrollController,
                      physics: const BouncingScrollPhysics(
                        parent: AlwaysScrollableScrollPhysics(),
                      ),
                      padding: const EdgeInsets.fromLTRB(
                        SokoniDimens.space20,
                        SokoniDimens.space16,
                        SokoniDimens.space20,
                        SokoniDimens.space24,
                      ),
                      child: builder(context),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      );
    },
  );
}
