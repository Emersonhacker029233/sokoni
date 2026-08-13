/// Route path constants — the single source of truth for paths used by
/// both [GoRouter]'s route tree and any `context.go`/`context.push` call
/// site, so a path never gets typo'd in two different ways.
abstract final class SokoniRoutes {
  static const splash = '/splash';
  static const motionGallery = '/motion-gallery';

  // Bottom nav tabs (StatefulShellRoute branches).
  static const home = '/home';
  static const search = '/search';
  static const sell = '/sell';
  static const orders = '/orders';
  static const profile = '/profile';
}
