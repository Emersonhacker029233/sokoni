import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni/core/utils/validators.dart';

void main() {
  group('SokoniValidators.optionalEmail', () {
    test('null is valid — email is always optional (C5)', () {
      expect(SokoniValidators.optionalEmail(null), isNull);
    });

    test('an empty string is valid — same reason', () {
      expect(SokoniValidators.optionalEmail(''), isNull);
    });

    test('a well-formed address is valid', () {
      expect(SokoniValidators.optionalEmail('amina@example.com'), isNull);
    });

    test('a malformed address is rejected', () {
      expect(SokoniValidators.optionalEmail('not-an-email'), isNotNull);
    });

    test('missing a domain is rejected', () {
      expect(SokoniValidators.optionalEmail('amina@'), isNotNull);
    });
  });
}
