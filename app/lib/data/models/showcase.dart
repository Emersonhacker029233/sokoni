import 'package:freezed_annotation/freezed_annotation.dart';

import 'product_summary.dart';
import 'seller_summary.dart';

part 'showcase.freezed.dart';
part 'showcase.g.dart';

/// "Showcase"/"Onyesho" (CLAUDE.md Part 3) — a vertical full-screen product
/// video. Mirrors `App\Http\Resources\ShowcaseResource`.
@freezed
abstract class Showcase with _$Showcase {
  const factory Showcase({
    required int id,
    @JsonKey(name: 'video_path') required String videoPath,
    @JsonKey(name: 'thumb_path') required String thumbPath,
    String? caption,
    required int duration,
    @Default(0) int views,
    SellerSummary? seller,
    ProductSummary? product,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _Showcase;

  factory Showcase.fromJson(Map<String, dynamic> json) => _$ShowcaseFromJson(json);
}
