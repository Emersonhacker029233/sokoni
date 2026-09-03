import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/product.dart';
import '../../../../shared/widgets/empty_state.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../../discovery/providers/discovery_providers.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// Every Update/Offer/Showcase that references a Listing must reference
/// one of the acting seller's own — this is the picker every composer
/// screen uses to enforce that at the UI layer (the API enforces it for
/// real, see StoreUpdateRequest/StoreOfferRequest/StoreShowcaseRequest).
Future<Product?> showMyProductPickerSheet(BuildContext context) {
  return showSokoniBottomSheet<Product>(
    context: context,
    initialChildSize: 0.6,
    builder: (context) => const _MyProductPickerContent(),
  );
}

class _MyProductPickerContent extends ConsumerWidget {
  const _MyProductPickerContent();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final myProductsAsync = ref.watch(myProductsProvider);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.pickProductTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        myProductsAsync.when(
          loading: () => const Padding(
            padding: EdgeInsets.symmetric(vertical: SokoniDimens.space32),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (error, _) => SokoniErrorState(
            message: '$error',
            onRetry: () => ref.invalidate(myProductsProvider),
          ),
          data: (products) {
            if (products.isEmpty) {
              return SokoniEmptyState(
                icon: Icons.inventory_2_outlined,
                title: l10n.pickProductEmptyTitle,
                message: l10n.pickProductEmptyMessage,
              );
            }
            return ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: products.length,
              separatorBuilder: (context, index) => const SizedBox(height: SokoniDimens.space8),
              itemBuilder: (context, index) {
                final product = products[index];
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: ClipRRect(
                    borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                    child: SizedBox(
                      width: 48,
                      height: 48,
                      child: product.coverImageUrl != null
                          ? SokoniNetworkImage(imageUrl: product.coverImageUrl!, fit: BoxFit.cover)
                          : Container(color: SokoniColors.surfaceAlt),
                    ),
                  ),
                  title: Text(product.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                  subtitle: Text(SokoniFormat.tzs(product.price)),
                  onTap: () => Navigator.of(context).pop(product),
                );
              },
            );
          },
        ),
      ],
    );
  }
}
