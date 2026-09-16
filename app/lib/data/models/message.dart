import 'package:freezed_annotation/freezed_annotation.dart';

part 'message.freezed.dart';
part 'message.g.dart';

/// Mirrors `App\Http\Resources\MessageResource`.
@freezed
abstract class ChatMessage with _$ChatMessage {
  const factory ChatMessage({
    required int id,
    @JsonKey(name: 'conversation_id') required int conversationId,
    @JsonKey(name: 'sender_id') required int senderId,
    String? body,
    String? attachment,
    @JsonKey(name: 'is_mine') @Default(false) bool isMine,
    @JsonKey(name: 'read_at') DateTime? readAt,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _ChatMessage;

  factory ChatMessage.fromJson(Map<String, dynamic> json) => _$ChatMessageFromJson(json);
}

/// Part 4 (client feedback): "tapping an image opens it full screen...
/// tapping a document opens or downloads it appropriately." There is no
/// separate type/mime column for `attachment` server-side (just a plain
/// URL string — see `messages` migration), so, same as
/// [SokoniNetworkImage.isSvgUrl]'s own dispatch rule, this is a pure
/// extension-of-the-URL check rather than trusting anything the server
/// would have to be changed to send.
extension ChatMessageAttachmentKind on ChatMessage {
  static const _imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg'];

  bool get isImageAttachment {
    final url = attachment;
    if (url == null) return false;
    final path = Uri.tryParse(url)?.path.toLowerCase() ?? '';
    return _imageExtensions.any(path.endsWith);
  }

  bool get isDocumentAttachment => attachment != null && !isImageAttachment;

  /// The original filename, best-effort, for showing next to a document
  /// chip — storage paths are hashed, so this is just whatever's left
  /// after the last "/", extension included.
  String? get attachmentFileName {
    final url = attachment;
    if (url == null) return null;
    final path = Uri.tryParse(url)?.path;
    if (path == null || path.isEmpty) return null;
    return path.split('/').last;
  }
}
