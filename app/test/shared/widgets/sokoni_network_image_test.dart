import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni/shared/widgets/sokoni_network_image.dart';

/// Tester feedback B1: real seller photos are always raster
/// (`ImageVariants` always writes `.jpg` server-side), but the demo
/// catalogue's placeholder products/updates/showcases are plain SVG,
/// which `CachedNetworkImage` can't decode at all — this dispatch rule is
/// what routes SVG URLs to `flutter_svg` instead. Covers the rule as a
/// pure function rather than mounting the real widget, which always makes
/// a genuine, un-mockable network call the moment it builds.
void main() {
  group('SokoniNetworkImage.isSvgUrl', () {
    test('true for a plain .svg URL', () {
      expect(SokoniNetworkImage.isSvgUrl('https://sokoni.co.tz/uploads/demo/products/tecno-spark-10.svg'), isTrue);
    });

    test('true regardless of case', () {
      expect(SokoniNetworkImage.isSvgUrl('https://sokoni.co.tz/uploads/demo/logos/kariakoo_mobile.SVG'), isTrue);
    });

    test('true even with a query string after the extension', () {
      expect(SokoniNetworkImage.isSvgUrl('https://sokoni.co.tz/uploads/demo/products/foo.svg?v=2'), isTrue);
    });

    test('false for a real seller jpg photo', () {
      expect(SokoniNetworkImage.isSvgUrl('https://sokoni.co.tz/uploads/products/1/card_abc123.jpg'), isFalse);
    });

    test('false for png and webp', () {
      expect(SokoniNetworkImage.isSvgUrl('https://sokoni.co.tz/uploads/sellers/logos/xyz.png'), isFalse);
      expect(SokoniNetworkImage.isSvgUrl('https://sokoni.co.tz/uploads/sellers/logos/xyz.webp'), isFalse);
    });

    test('false for an unparsable string rather than throwing', () {
      expect(SokoniNetworkImage.isSvgUrl(''), isFalse);
    });
  });
}
