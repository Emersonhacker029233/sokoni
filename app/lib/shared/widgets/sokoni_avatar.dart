import 'package:flutter/material.dart';

import 'sokoni_network_image.dart';

/// A `CircleAvatar` that also handles SVG — the same gap [SokoniNetworkImage]
/// closed for product/media photos, but for shop logos and user avatars,
/// which used to go through `CircleAvatar.backgroundImage` (an
/// `ImageProvider`, not a widget) and so couldn't just delegate to
/// [SokoniNetworkImage] directly.
///
/// Part A (client feedback, re-diagnosed): the demo seeder writes shop
/// logos as `.svg` too (`InitialsLogo::svg()`, same generator family as the
/// product placeholders [SokoniNetworkImage] already covers) — every
/// `CircleAvatar(backgroundImage: CachedNetworkImageProvider(...))` /
/// `NetworkImage(...)` call site showing a shop logo or user avatar was
/// still the exact bug [SokoniNetworkImage] was built to fix, just via a
/// different API that fix never reached. This is why the app kept showing
/// blank shop avatars everywhere (feed headers, shop profiles, comments)
/// even after that first pass. Routes both the SVG and raster case through
/// [SokoniNetworkImage] itself (clipped to a circle) rather than
/// maintaining a second copy of its loading/placeholder/error handling —
/// including its default "never blank on a failed load" placeholder.
class SokoniAvatar extends StatelessWidget {
  const SokoniAvatar({
    required this.imageUrl,
    required this.radius,
    this.backgroundColor,
    this.fallbackIcon,
    this.fallbackIconSize,
    super.key,
  });

  final String? imageUrl;
  final double radius;
  final Color? backgroundColor;
  final IconData? fallbackIcon;
  final double? fallbackIconSize;

  @override
  Widget build(BuildContext context) {
    final url = imageUrl;

    if (url == null) {
      return CircleAvatar(
        radius: radius,
        backgroundColor: backgroundColor,
        child: fallbackIcon != null
            ? Icon(fallbackIcon, size: fallbackIconSize)
            : null,
      );
    }

    final diameter = radius * 2;
    return CircleAvatar(
      radius: radius,
      backgroundColor: backgroundColor,
      child: ClipOval(
        child: SokoniNetworkImage(
          imageUrl: url,
          width: diameter,
          height: diameter,
          fit: BoxFit.cover,
        ),
      ),
    );
  }
}
