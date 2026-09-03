import 'package:intl/intl.dart';

/// TZS currency + phone-number formatting helpers (CLAUDE.md working
/// rules: "TSh 45,000" with thousands separators and no decimals; phone
/// numbers stored E.164, displayed local).
abstract final class SokoniFormat {
  static final _tzs = NumberFormat.decimalPattern('en_US');

  /// `1025000` → `"TSh 1,025,000"`.
  static String tzs(num amount) => 'TSh ${_tzs.format(amount)}';

  /// `-6.79` → `"2.3 km away"` given a precomputed distance in km.
  static String distanceKm(double km) {
    if (km < 1) return '${(km * 1000).round()} m away';
    return '${km.toStringAsFixed(1)} km away';
  }

  /// `"+255754123456"` → `"0754 123 456"`. Falls back to the raw input if
  /// it isn't a recognised `+255` E.164 number.
  static String phoneLocal(String e164) {
    final match = RegExp(r'^\+255(\d{9})$').firstMatch(e164);
    if (match == null) return e164;
    final digits = match.group(1)!;
    return '0${digits.substring(0, 3)} ${digits.substring(3, 6)} ${digits.substring(6, 9)}';
  }

  /// `"+255754123456"` → `"0754 *** 456"` — the middle group hidden, for
  /// the "Sign in" flow's "Welcome back" screen (CLAUDE.md restructure,
  /// 2026-08-25): the number is already known server-side (it's why the
  /// screen is showing this message at all), so the masking is a small
  /// privacy touch, not a security boundary.
  static String phoneMasked(String e164) {
    final match = RegExp(r'^\+255(\d{9})$').firstMatch(e164);
    if (match == null) return e164;
    final digits = match.group(1)!;
    return '0${digits.substring(0, 3)} *** ${digits.substring(6, 9)}';
  }

  /// `"0754123456"` or `"754123456"` → `"+255754123456"`. Returns null if
  /// the input isn't a plausible Tanzanian mobile number.
  static String? phoneToE164(String local) {
    final digits = local.replaceAll(RegExp(r'\D'), '');
    if (digits.length == 9 && RegExp(r'^[67]').hasMatch(digits)) {
      return '+255$digits';
    }
    if (digits.length == 10 && digits.startsWith('0')) {
      return '+255${digits.substring(1)}';
    }
    if (digits.length == 12 && digits.startsWith('255')) {
      return '+$digits';
    }
    return null;
  }
}
