/// Mirrors `App\Support\OpeningHours` — the seven-day, Monday-first key
/// order the backend's own `parse()` always returns, so the app renders
/// the identical day sequence the website does.
class SokoniOpeningHours {
  static const List<String> days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

  /// {"open": "HH:MM", "close": "HH:MM"} for the given day, or null if
  /// closed/unset.
  static Map<String, String>? hoursFor(Map<String, dynamic>? openingHours, String day) {
    final value = openingHours?[day];
    if (value is Map && value['open'] is String && value['close'] is String) {
      return {'open': value['open'] as String, 'close': value['close'] as String};
    }
    return null;
  }
}
