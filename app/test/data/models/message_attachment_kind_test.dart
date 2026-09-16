import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/data/models/message.dart';

/// Part 4 (client feedback): "tapping an image opens it full screen...
/// tapping a document opens or downloads it appropriately." There is no
/// stored type/mime column for `attachment` server-side — just a URL —
/// so this pure extension-of-the-URL dispatch (mirroring
/// SokoniNetworkImage.isSvgUrl's own approach) is the only signal either
/// surface has to tell an image from a document.
void main() {
  ChatMessage messageWith(String? attachment) {
    return ChatMessage(id: 1, conversationId: 1, senderId: 1, attachment: attachment);
  }

  group('isImageAttachment / isDocumentAttachment', () {
    for (final ext in ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg']) {
      test('$ext is an image, not a document', () {
        final message = messageWith('https://sokoni.co.tz/uploads/chat/photo$ext');
        expect(message.isImageAttachment, isTrue);
        expect(message.isDocumentAttachment, isFalse);
      });
    }

    for (final ext in ['.pdf', '.doc', '.docx']) {
      test('$ext is a document, not an image', () {
        final message = messageWith('https://sokoni.co.tz/uploads/chat/invoice$ext');
        expect(message.isImageAttachment, isFalse);
        expect(message.isDocumentAttachment, isTrue);
      });
    }

    test('a message with no attachment is neither', () {
      final message = messageWith(null);
      expect(message.isImageAttachment, isFalse);
      expect(message.isDocumentAttachment, isFalse);
    });

    test('classification is case-insensitive and ignores a query string', () {
      final message = messageWith('https://sokoni.co.tz/uploads/chat/PHOTO.JPG?v=2');
      expect(message.isImageAttachment, isTrue);
    });
  });

  group('attachmentFileName', () {
    test('extracts the last path segment', () {
      final message = messageWith('https://sokoni.co.tz/uploads/chat/a1b2c3-invoice.pdf');
      expect(message.attachmentFileName, 'a1b2c3-invoice.pdf');
    });

    test('is null when there is no attachment', () {
      expect(messageWith(null).attachmentFileName, isNull);
    });
  });
}
