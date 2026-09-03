import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/product.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../auth/presentation/sign_in_prompt_sheet.dart';
import '../providers/order_providers.dart';
import '../../../shared/widgets/sokoni_network_image.dart';

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final lines = ref.watch(cartProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.cartTitle)),
      body: lines.isEmpty
          ? SokoniEmptyState(
              icon: Icons.shopping_bag_outlined,
              title: l10n.cartEmptyTitle,
              message: l10n.cartEmptyBody,
            )
          : Column(
              children: [
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.all(SokoniDimens.space16),
                    itemCount: lines.length,
                    separatorBuilder: (_, _) => const Divider(),
                    itemBuilder: (context, index) {
                      final line = lines[index];
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: ClipRRect(
                          borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                          child: line.product.coverImageUrl == null
                              ? Container(
                                  width: 48,
                                  height: 48,
                                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                                )
                              : SokoniNetworkImage(
                                  imageUrl: line.product.coverImageUrl!,
                                  width: 48,
                                  height: 48,
                                  fit: BoxFit.cover,
                                  placeholder: (context, url) =>
                                      Container(color: Theme.of(context).colorScheme.surfaceContainerHighest),
                                ),
                        ),
                        title: Text(line.product.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                        subtitle: Text(SokoniFormat.tzs(line.product.price)),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline_rounded),
                              onPressed: () => ref
                                  .read(cartProvider.notifier)
                                  .setQty(line.product.id, line.qty - 1),
                            ),
                            Text('${line.qty}'),
                            IconButton(
                              icon: const Icon(Icons.add_circle_outline_rounded),
                              onPressed: () => ref
                                  .read(cartProvider.notifier)
                                  .setQty(line.product.id, line.qty + 1),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(SokoniDimens.space16),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(l10n.cartSubtotal, style: Theme.of(context).textTheme.titleMedium),
                          Text(
                            SokoniFormat.tzs(lines.fold(0, (sum, line) => sum + line.lineTotal)),
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                          ),
                        ],
                      ),
                      const SizedBox(height: SokoniDimens.space16),
                      FilledButton(
                        onPressed: () {
                          if (ref.read(authStateProvider).isAuthenticated) {
                            context.push(SokoniRoutes.checkout);
                          } else {
                            showSignInPrompt(context, message: l10n.guestPromptOrder);
                          }
                        },
                        child: Text(l10n.cartCheckout),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}
