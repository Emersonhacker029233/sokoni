import 'package:url_launcher/url_launcher.dart';

/// Opens turn-by-turn navigation in Google Maps (app if installed, else
/// browser). Drivers already know and trust it; the in-app map is only an
/// overview.
Future<bool> openNavigation(double lat, double lng) {
  final uri = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving');
  return launchUrl(uri, mode: LaunchMode.externalApplication);
}

Future<bool> openDialer(String phone) => launchUrl(Uri(scheme: 'tel', path: phone));

Future<bool> openSupportEmail() => launchUrl(Uri(scheme: 'mailto', path: 'support@sokoni.co.tz'));
