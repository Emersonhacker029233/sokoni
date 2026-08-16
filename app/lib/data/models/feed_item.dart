import 'offer.dart';
import 'product.dart';
import 'showcase.dart';

enum FeedItemType { product, offer, showcase }

/// A tagged union over the "For You" feed's three card types — mirrors
/// `App\Http\Resources\FeedItemResource`. A plain class, not `@freezed`:
/// this is a thin, write-once envelope with no need for `copyWith`/value
/// equality, so it isn't worth the extra codegen surface.
class FeedItem {
  const FeedItem.product(Product this.product) : type = FeedItemType.product, offer = null, showcase = null;

  const FeedItem.offer(Offer this.offer) : type = FeedItemType.offer, product = null, showcase = null;

  const FeedItem.showcase(Showcase this.showcase) : type = FeedItemType.showcase, product = null, offer = null;

  factory FeedItem.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return switch (json['type'] as String) {
      'product' => FeedItem.product(Product.fromJson(data)),
      'offer' => FeedItem.offer(Offer.fromJson(data)),
      'showcase' => FeedItem.showcase(Showcase.fromJson(data)),
      final other => throw FormatException('Unknown feed item type: $other'),
    };
  }

  final FeedItemType type;
  final Product? product;
  final Offer? offer;
  final Showcase? showcase;
}
