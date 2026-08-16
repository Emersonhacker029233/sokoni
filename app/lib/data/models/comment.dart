import 'package:freezed_annotation/freezed_annotation.dart';

part 'comment.freezed.dart';
part 'comment.g.dart';

/// Mirrors `App\Http\Resources\CommentResource`.
@freezed
abstract class ProductComment with _$ProductComment {
  const factory ProductComment({
    required int id,
    @JsonKey(name: 'product_id') required int productId,
    @JsonKey(name: 'parent_id') int? parentId,
    required String body,
    required CommentAuthor user,
    @JsonKey(name: 'is_from_seller') @Default(false) bool isFromSeller,
    @Default(<ProductComment>[]) List<ProductComment> replies,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _ProductComment;

  factory ProductComment.fromJson(Map<String, dynamic> json) => _$ProductCommentFromJson(json);
}

@freezed
abstract class CommentAuthor with _$CommentAuthor {
  const factory CommentAuthor({required int id, required String name, String? avatar}) = _CommentAuthor;

  factory CommentAuthor.fromJson(Map<String, dynamic> json) => _$CommentAuthorFromJson(json);
}
