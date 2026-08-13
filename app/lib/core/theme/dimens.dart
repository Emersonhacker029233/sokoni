/// Shared spacing, radius and sizing constants for the Sokoni design system.
abstract final class SokoniDimens {
  // Spacing scale (4dp base grid).
  static const double space4 = 4;
  static const double space8 = 8;
  static const double space12 = 12;
  static const double space16 = 16;
  static const double space20 = 20;
  static const double space24 = 24;
  static const double space32 = 32;
  static const double space40 = 40;
  static const double space48 = 48;

  // Corner radii.
  static const double radiusChip = 12;
  static const double radiusField = 12;
  static const double radiusCard = 16;
  static const double radiusSheet = 24;
  static const double radiusButton = 28;

  // Minimum tap target (accessibility floor).
  static const double minTapTarget = 44;

  // Ambient shadow used only on floating elements (no shadows elsewhere).
  static const double ambientBlurRadius = 24;
  static const double ambientShadowOpacity = 0.04;

  // Bottom nav.
  static const double navBarHeight = 64;
  static const double navFabSize = 56;
}
