import 'package:freezed_annotation/freezed_annotation.dart';

import 'user.dart';

part 'review.freezed.dart';
part 'review.g.dart';

/// Mirrors `App\Http\Resources\ReviewResource`.
@freezed
abstract class Review with _$Review {
  const factory Review({
    required int id,
    @JsonKey(name: 'order_id') required int orderId,
    required int rating,
    String? comment,
    String? reply,
    @JsonKey(name: 'replied_at') DateTime? repliedAt,
    SokoniUser? buyer,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _Review;

  factory Review.fromJson(Map<String, dynamic> json) => _$ReviewFromJson(json);
}
