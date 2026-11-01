import 'package:intl/intl.dart';

/// TZS, phone and distance formatting, same rules as the customer app:
/// `TSh 45,000` with separators and no decimals, phones stored E.164 and
/// shown local.
abstract final class DriverFormat {
  static final _tzs = NumberFormat.decimalPattern('en_US');

  static String tzs(num amount) => 'TSh ${_tzs.format(amount)}';

  /// `0.42` → `420 m`, `3.27` → `3.3 km`.
  static String km(double km) => km < 1 ? '${(km * 1000).round()} m' : '${km.toStringAsFixed(1)} km';

  /// `+255754123456` → `0754 123 456`.
  static String phoneLocal(String e164) {
    final match = RegExp(r'^\+255(\d{9})$').firstMatch(e164);
    if (match == null) return e164;
    final d = match.group(1)!;
    return '0${d.substring(0, 3)} ${d.substring(3, 6)} ${d.substring(6)}';
  }

  /// `0754 123 456`, `754123456` or `255754123456` → `+255754123456`;
  /// null when it isn't a Tanzanian mobile number.
  static String? phoneToE164(String input) {
    final digits = input.replaceAll(RegExp(r'\D'), '');
    String? local;
    if (digits.length == 9) local = digits;
    if (digits.length == 10 && digits.startsWith('0')) local = digits.substring(1);
    if (digits.length == 12 && digits.startsWith('255')) local = digits.substring(3);
    if (local == null || !RegExp(r'^[67]\d{8}$').hasMatch(local)) return null;
    return '+255$local';
  }

  static String time(DateTime t, String locale) => DateFormat.Hm(locale).format(t.toLocal());

  static String date(DateTime t, String locale) => DateFormat.yMMMd(locale).format(t.toLocal());

  static String dateTime(DateTime t, String locale) => DateFormat.yMMMd(locale).add_Hm().format(t.toLocal());

  /// `2026-11-01` → `Sun 1` style label for the earnings bar chart.
  static String shortDay(String isoDate, String locale) {
    final d = DateTime.tryParse(isoDate);
    return d == null ? isoDate : DateFormat.E(locale).format(d);
  }
}
