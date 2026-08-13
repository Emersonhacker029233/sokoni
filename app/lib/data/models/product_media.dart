import 'package:freezed_annotation/freezed_annotation.dart';

part 'product_media.freezed.dart';
part 'product_media.g.dart';

/// Mirrors `App\Http\Resources\ProductMediaResource`.
@freezed
abstract class ProductMediaItem with _$ProductMediaItem {
  const factory ProductMediaItem({
    required int id,
    required String type, // 'image' | 'video'
    required String path,
    @JsonKey(name: 'thumb_path') String? thumbPath,
    @JsonKey(name: 'card_path') String? cardPath,
    int? duration,
    @Default(0) int sort,
  }) = _ProductMediaItem;

  factory ProductMediaItem.fromJson(Map<String, dynamic> json) => _$ProductMediaItemFromJson(json);
}

extension ProductMediaItemX on ProductMediaItem {
  bool get isVideo => type == 'video';
}
