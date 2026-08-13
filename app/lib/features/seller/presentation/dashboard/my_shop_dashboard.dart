import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/product.dart';
import '../../../../shared/widgets/empty_state.dart';
import '../../../discovery/providers/discovery_providers.dart';

final myProductsProvider = FutureProvider.autoDispose<List<Product>>((ref) async {
  final result = await ref.watch(productRepositoryProvider).myProducts();
  return result.items;
});

/// Seller's own product list — CLAUDE.md feature 4/7: sellers, pending or
/// verified, can build their shop and add products; pending sellers' items
/// just stay hidden from the public feed until verified (the status badge
/// above this list, in `sell_screen.dart`, already communicates that).
class MyShopDashboard extends ConsumerWidget {
  const MyShopDashboard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final productsAsync = ref.watch(myProductsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(l10n.myShopProductsTitle, style: Theme.of(context).textTheme.titleMedium),
              FilledButton.icon(
                onPressed: () => context.push(SokoniRoutes.newProduct),
                icon: const Icon(Icons.add_rounded, size: 18),
                label: Text(l10n.myShopAddProduct),
              ),
            ],
          ),
        ),
        const SizedBox(height: SokoniDimens.space12),
        productsAsync.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(SokoniDimens.space24),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (error, _) => Padding(
            padding: const EdgeInsets.all(SokoniDimens.space16),
            child: Text(error is ApiException ? error.message : '$error'),
          ),
          data: (products) {
            if (products.isEmpty) {
              return SokoniEmptyState(
                icon: Icons.inventory_2_outlined,
                title: l10n.myShopNoProductsTitle,
                message: l10n.myShopNoProductsBody,
                actionLabel: l10n.myShopAddProduct,
                onAction: () => context.push(SokoniRoutes.newProduct),
              );
            }
            return ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
              itemCount: products.length,
              separatorBuilder: (_, _) => const Divider(),
              itemBuilder: (context, index) => _ProductRow(product: products[index]),
            );
          },
        ),
      ],
    );
  }
}

class _ProductRow extends ConsumerWidget {
  const _ProductRow({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final cover = product.media.isEmpty ? null : product.media.first;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
        child: cover == null
            ? Container(width: 48, height: 48, color: Theme.of(context).colorScheme.surfaceContainerHighest)
            : Image.network(cover.thumbPath ?? cover.path, width: 48, height: 48, fit: BoxFit.cover),
      ),
      title: Text(product.title, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        '${SokoniFormat.tzs(product.price)}'
        '${product.isActive ? '' : ' · ${l10n.myShopInactive}'}',
      ),
      trailing: PopupMenuButton<String>(
        onSelected: (action) {
          if (action == 'edit') {
            context.push(SokoniRoutes.editProduct(product.id));
          } else if (action == 'delete') {
            _confirmDelete(context, ref, product);
          }
        },
        itemBuilder: (context) => [
          PopupMenuItem(value: 'edit', child: Text(l10n.myShopEdit)),
          PopupMenuItem(value: 'delete', child: Text(l10n.myShopDelete)),
        ],
      ),
      onTap: () => context.push(SokoniRoutes.editProduct(product.id)),
    );
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref, Product product) async {
    final l10n = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.myShopDelete),
        content: Text(l10n.myShopConfirmDelete(product.title)),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.commonCancel)),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l10n.myShopDelete)),
        ],
      ),
    );
    if (confirmed != true) return;
    await ref.read(productRepositoryProvider).deleteProduct(product.id);
    ref.invalidate(myProductsProvider);
  }
}
