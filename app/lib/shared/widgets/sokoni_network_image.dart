import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Drop-in replacement for [CachedNetworkImage] that also handles SVG.
///
/// Every real seller photo is raster (`ImageVariants` always writes `.jpg`
/// server-side, regardless of what was uploaded), but the demo catalogue's
/// placeholder products/updates/showcases are generated as plain SVG —
/// [CachedNetworkImage]'s decoder can't read that at all (it isn't a
/// supported raster format), so any product carrying one rendered as a
/// blank/broken image in the app specifically, even though the same URL
/// displays fine on the website (browsers decode `<img src="*.svg">`
/// natively) — tester feedback B1. Dispatches purely on the URL's file
/// extension: everything that isn't `.svg` goes through the exact same
/// [CachedNetworkImage] path as before, so this changes nothing for real
/// photos.
class SokoniNetworkImage extends StatelessWidget {
  const SokoniNetworkImage({
    required this.imageUrl,
    this.fit,
    this.width,
    this.height,
    this.placeholder,
    this.errorWidget,
    super.key,
  });

  final String imageUrl;
  final BoxFit? fit;
  final double? width;
  final double? height;
  final Widget Function(BuildContext, String)? placeholder;
  final Widget Function(BuildContext, String, Object)? errorWidget;

  /// Pure and public so this dispatch rule has a real, fast, network-free
  /// unit test — [SokoniNetworkImage] itself always makes a genuine network
  /// call the moment it builds, which isn't safe to exercise in a plain
  /// widget test in this codebase (see DECISIONS.md: a real request left
  /// in flight past `flutter test`'s teardown fails as "a Timer is still
  /// pending", and `cached_network_image`/`flutter_svg`'s network fetches
  /// go through Flutter's own `HttpClient`, not this app's overridable
  /// `dioProvider`).
  static bool isSvgUrl(String url) =>
      Uri.tryParse(url)?.path.toLowerCase().endsWith('.svg') ?? false;

  @override
  Widget build(BuildContext context) {
    // Part A (client feedback): "a failed image must never render as blank
    // space." Most call sites across the app never passed their own
    // `errorWidget` at all — `CachedNetworkImage` renders nothing without
    // one, and neither does `flutter_svg` in any visible way — so a 404,
    // a stale host, or a genuinely corrupt file all looked identical to
    // "no image was ever there," which is exactly what made this bug read
    // as "inconsistently missing" rather than "sometimes errors." A caller
    // that does pass its own still wins; this only fills the gap.
    final effectivePlaceholder = placeholder ?? _defaultPlaceholder;
    final effectiveError = errorWidget ?? _defaultError;

    if (isSvgUrl(imageUrl)) {
      return SvgPicture.network(
        imageUrl,
        fit: fit ?? BoxFit.contain,
        width: width,
        height: height,
        placeholderBuilder: (context) =>
            effectivePlaceholder(context, imageUrl),
        errorBuilder: (context, error, stackTrace) =>
            effectiveError(context, imageUrl, error),
      );
    }

    return CachedNetworkImage(
      imageUrl: imageUrl,
      fit: fit,
      width: width,
      height: height,
      placeholder: effectivePlaceholder,
      errorWidget: effectiveError,
    );
  }

  static Widget _defaultPlaceholder(BuildContext context, String url) {
    return Container(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
    );
  }

  static Widget _defaultError(BuildContext context, String url, Object error) {
    return Container(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      alignment: Alignment.center,
      child: Icon(
        Icons.image_not_supported_outlined,
        color: Theme.of(context).hintColor,
      ),
    );
  }
}
