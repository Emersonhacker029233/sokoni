import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni/core/utils/opening_hours.dart';

/// D3 (tester feedback): the shop profile screen's opening-hours parsing,
/// covered as a pure function — mounting the full screen would need
/// mocking its entire provider tree (seller profile, dashboard, reviews),
/// which has no existing test harness to build on for what's a small,
/// self-contained parsing rule.
void main() {
  group('SokoniOpeningHours.hoursFor', () {
    test('returns the open/close pair for a day with real hours', () {
      final hours = SokoniOpeningHours.hoursFor({
        'monday': {'open': '08:00', 'close': '18:00'},
      }, 'monday');

      expect(hours, {'open': '08:00', 'close': '18:00'});
    });

    test('returns null for a day explicitly closed', () {
      final hours = SokoniOpeningHours.hoursFor({'sunday': null}, 'sunday');

      expect(hours, isNull);
    });

    test('returns null for a day missing from the map entirely', () {
      final hours = SokoniOpeningHours.hoursFor({'monday': {'open': '08:00', 'close': '18:00'}}, 'tuesday');

      expect(hours, isNull);
    });

    test('returns null when the whole opening_hours map is null', () {
      expect(SokoniOpeningHours.hoursFor(null, 'monday'), isNull);
    });

    test('days lists all seven in Monday-first order, matching the backend', () {
      expect(SokoniOpeningHours.days, [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
      ]);
    });
  });
}
