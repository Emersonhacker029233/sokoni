// Generates the Sokoni Driver launcher icon from the customer app's icon:
// the same bag mark, inverted onto Sokoni black, so the two apps are easy
// to tell apart on one home screen while sharing the brand.
//
//   dart run tool/make_icon.dart
//   dart run flutter_launcher_icons
import 'dart:io';

import 'package:image/image.dart' as img;

const _black = (0x0A, 0x0A, 0x0A);

void main() {
  final source = img.decodePng(File('../app/assets/brand/app_icon.png').readAsBytesSync());
  if (source == null) {
    stderr.writeln('Could not read ../app/assets/brand/app_icon.png');
    exit(1);
  }

  final full = img.Image(width: source.width, height: source.height, numChannels: 4);
  final foreground = img.Image(width: source.width, height: source.height, numChannels: 4);

  for (final p in source) {
    final r = p.r.toInt(), g = p.g.toInt(), b = p.b.toInt();
    final isYellow = r > 180 && g > 150 && b < 120;
    final lightness = (r + g + b) / 3;

    final (int, int, int) colour;
    final int alpha;
    if (isYellow) {
      colour = (r, g, b);
      alpha = 255;
    } else {
      // White background becomes black, the black mark becomes white;
      // anti-aliased greys invert smoothly.
      final v = 255 - lightness.round();
      colour = (
        _black.$1 + ((v - _black.$1) * (v / 255)).round(),
        _black.$2 + ((v - _black.$2) * (v / 255)).round(),
        _black.$3 + ((v - _black.$3) * (v / 255)).round(),
      );
      alpha = (255 - lightness).round().clamp(0, 255);
    }

    full.setPixelRgba(p.x, p.y, colour.$1, colour.$2, colour.$3, 255);
    foreground.setPixelRgba(p.x, p.y, isYellow ? r : 255, isYellow ? g : 255, isYellow ? b : 255, isYellow ? 255 : alpha);
  }

  File('assets/brand/driver_icon.png').writeAsBytesSync(img.encodePng(full));
  File('assets/brand/driver_icon_foreground.png').writeAsBytesSync(img.encodePng(foreground));
  stdout.writeln('Wrote assets/brand/driver_icon.png and driver_icon_foreground.png');
}
