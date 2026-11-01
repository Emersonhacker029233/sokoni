import 'package:flutter/material.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/repositories/driver_repository.dart';
import '../../features/home/work_controller.dart';

/// Full-width yellow button with an in-place spinner while [loading].
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({super.key, required this.label, required this.onPressed, this.loading = false, this.icon});

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return ElevatedButton(
      onPressed: loading ? null : onPressed,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 180),
        child: loading
            ? const SizedBox(
                key: ValueKey('spinner'),
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2.5, color: SokoniColors.onYellow),
              )
            : Row(
                key: const ValueKey('label'),
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[Icon(icon, size: 20), const SizedBox(width: 8)],
                  Flexible(child: Text(label, textAlign: TextAlign.center)),
                ],
              ),
      ),
    );
  }
}

/// No blank screens: an icon in a soft circle, one sentence, one action.
class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.body, this.actionLabel, this.onAction});

  final IconData icon;
  final String title;
  final String? body;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(color: theme.colorScheme.surfaceContainerHighest, shape: BoxShape.circle),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Icon(icon, size: 44, color: theme.colorScheme.onSurface),
                  Positioned(
                    right: 18,
                    top: 18,
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: SokoniDimens.space20),
            Text(title, style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            if (body != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(body!, style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurface.withValues(alpha: 0.7)), textAlign: TextAlign.center),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: SokoniDimens.space24),
              SizedBox(width: 240, child: ElevatedButton(onPressed: onAction, child: Text(actionLabel!))),
            ],
          ],
        ),
      ),
    );
  }
}

/// Error with a retry, using the server's localised message when it has one.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (error is DeliveryDisabledException) {
      return EmptyState(
        icon: Icons.two_wheeler_outlined,
        title: l.deliveryUnavailableTitle,
        body: l.deliveryUnavailableBody,
        actionLabel: l.checkAgain,
        onAction: onRetry,
      );
    }
    return EmptyState(
      icon: error is NetworkException || error is RequestTimeoutException ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
      title: errorMessage(context, error),
      actionLabel: l.retry,
      onAction: onRetry,
    );
  }
}

/// Human message for anything a controller throws.
String errorMessage(BuildContext context, Object error) {
  final l = AppLocalizations.of(context);
  return switch (error) {
    NeedsConnectionException() => l.needsConnection,
    LocationUnavailableException() => l.locationUnavailable,
    DeliveryDisabledException() => l.deliveryUnavailableBody,
    ApiException(:final message) => message,
    _ => l.errorGeneric,
  };
}

void showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(errorMessage(context, error))));
}

void showMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

/// Placeholder block that gently pulses while content loads. Each screen
/// composes these into the shape of its real layout.
class SkeletonBox extends StatefulWidget {
  const SkeletonBox({super.key, this.width, required this.height, this.radius = SokoniDimens.radiusField});

  final double? width;
  final double height;
  final double radius;

  @override
  State<SkeletonBox> createState() => _SkeletonBoxState();
}

class _SkeletonBoxState extends State<SkeletonBox> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _controller.value = 0.5;
    } else if (!_controller.isAnimating) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final base = Theme.of(context).colorScheme.surfaceContainerHighest;
    final outline = Theme.of(context).colorScheme.outline;
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: Color.lerp(base, outline, _controller.value * 0.6),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

/// Bordered card used across the app (no drop shadows, 16dp radius).
class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.child, this.padding = const EdgeInsets.all(SokoniDimens.space16), this.onTap});

  final Widget child;
  final EdgeInsets padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(onTap: onTap, child: Padding(padding: padding, child: child)),
    );
  }
}

/// Small rounded label, yellow when [highlight].
class Pill extends StatelessWidget {
  const Pill({super.key, required this.label, this.highlight = false, this.color, this.icon});

  final String label;
  final bool highlight;
  final Color? color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bg = color ?? (highlight ? SokoniColors.sokoniYellow : theme.colorScheme.surfaceContainerHighest);
    final fg = color != null || highlight ? SokoniColors.onYellow : theme.colorScheme.onSurface;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
        border: highlight || color != null ? null : Border.all(color: theme.colorScheme.outline),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 14, color: fg), const SizedBox(width: 4)],
          Text(label, style: theme.textTheme.labelSmall?.copyWith(color: fg, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

/// Yellow-tinted strip for offline / saved-data notices.
class NoticeBanner extends StatelessWidget {
  const NoticeBanner({super.key, required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: SokoniColors.sokoniYellow.withValues(alpha: 0.18),
      padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16, vertical: SokoniDimens.space8),
      child: Row(
        children: [
          Icon(icon, size: 18),
          const SizedBox(width: SokoniDimens.space8),
          Expanded(child: Text(text, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurface))),
        ],
      ),
    );
  }
}

/// Label/value row for summaries.
class InfoRow extends StatelessWidget {
  const InfoRow({super.key, required this.label, required this.value, this.bold = false});

  final String label;
  final String value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(child: Text(label, style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurface.withValues(alpha: 0.7)))),
          Text(value, style: bold ? theme.textTheme.titleMedium : theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
