import 'package:freezed_annotation/freezed_annotation.dart';

import 'product_summary.dart';
import 'seller_summary.dart';

part 'social_update.freezed.dart';
part 'social_update.g.dart';

/// "Update"/"Taarifa" (CLAUDE.md Part 3) — a 24h shop notice, Instagram-story
/// style. Mirrors `App\Http\Resources\UpdateResource`. Named `SocialUpdate`
/// (not `Update`) to avoid colliding with Dart's own `Function` naming and
/// Flutter's `State.update`.
@freezed
abstract class SocialUpdate with _$SocialUpdate {
  const factory SocialUpdate({
    required int id,
    required String type, // 'image' | 'video'
    @JsonKey(name: 'media_path') required String mediaPath,
    @JsonKey(name: 'thumb_path') String? thumbPath,
    String? caption,
    SellerSummary? seller,
    ProductSummary? product,
    @JsonKey(name: 'expires_at') required DateTime expiresAt,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _SocialUpdate;

  factory SocialUpdate.fromJson(Map<String, dynamic> json) => _$SocialUpdateFromJson(json);
}

extension SocialUpdateX on SocialUpdate {
  bool get isVideo => type == 'video';
}
