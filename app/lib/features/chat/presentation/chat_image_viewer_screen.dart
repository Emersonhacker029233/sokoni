import 'package:flutter/material.dart';

import '../../../shared/widgets/sokoni_network_image.dart';

/// Part 4 (client feedback): "Tapping an image opens it full screen, with
/// pinch to zoom, drag to pan, and swipe or a close button to dismiss.
/// Where several images exist in a conversation, allow swiping between
/// them... Load the full-resolution version only when opened, keeping
/// the thumbnail in the thread."
///
/// The thread's own bubble already requests the attachment at a small,
/// capped `width` (see `_MessageBubble`) — [SokoniNetworkImage] here is
/// given no width/height at all, so it decodes/displays the same URL at
/// its natural resolution, a fresh request this screen makes only once
/// actually opened. There is no separate stored thumb/full variant for
/// chat attachments the way `ProductMedia` has (just one URL per
/// message — see the `messages` table), so "full-resolution on open" is
/// this: a size-unconstrained request that only ever happens here.
class ChatImageViewerScreen extends StatefulWidget {
  const ChatImageViewerScreen({required this.imageUrls, required this.initialIndex, super.key});

  final List<String> imageUrls;
  final int initialIndex;

  @override
  State<ChatImageViewerScreen> createState() => _ChatImageViewerScreenState();
}

class _ChatImageViewerScreenState extends State<ChatImageViewerScreen> {
  late final _pageController = PageController(initialPage: widget.initialIndex);

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            itemCount: widget.imageUrls.length,
            itemBuilder: (context, index) => _ZoomableImagePage(imageUrl: widget.imageUrls[index]),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Align(
                alignment: Alignment.topRight,
                child: IconButton(
                  icon: const Icon(Icons.close, color: Colors.white),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ZoomableImagePage extends StatefulWidget {
  const _ZoomableImagePage({required this.imageUrl});

  final String imageUrl;

  @override
  State<_ZoomableImagePage> createState() => _ZoomableImagePageState();
}

class _ZoomableImagePageState extends State<_ZoomableImagePage> {
  final _transformController = TransformationController();
  double _dragDy = 0;
  bool _zoomed = false;

  bool get _isZoomed {
    // getMaxScaleOnAxis() on an identity matrix is exactly 1.0 — a small
    // epsilon avoids treating floating-point noise from a just-completed
    // pinch-back-to-1x as "still zoomed".
    return _transformController.value.getMaxScaleOnAxis() > 1.01;
  }

  void _onInteraction(ScaleEndDetails _) {
    final zoomed = _isZoomed;
    if (zoomed != _zoomed) setState(() => _zoomed = zoomed);
  }

  void _onVerticalDragUpdate(DragUpdateDetails details) {
    if (_zoomed) return;
    setState(() => _dragDy += details.delta.dy);
  }

  void _onVerticalDragEnd(DragEndDetails details) {
    if (_zoomed) return;
    final velocity = details.primaryVelocity ?? 0;
    if (_dragDy.abs() > 120 || velocity.abs() > 800) {
      Navigator.of(context).pop();
      return;
    }
    setState(() => _dragDy = 0);
  }

  @override
  void dispose() {
    _transformController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Fades toward transparent as the swipe-to-dismiss drag progresses,
    // so the gesture reads as "pulling the image away" rather than a
    // hard cut — matches this app's own "nothing over 400ms, no jank"
    // motion rules well enough for a drag-driven (not timed) effect.
    final dragProgress = (_dragDy.abs() / 300).clamp(0.0, 1.0);

    return GestureDetector(
      onVerticalDragUpdate: _onVerticalDragUpdate,
      onVerticalDragEnd: _onVerticalDragEnd,
      child: Opacity(
        opacity: 1 - dragProgress * 0.7,
        child: Transform.translate(
          offset: Offset(0, _dragDy),
          child: InteractiveViewer(
            transformationController: _transformController,
            // Disabled until actually zoomed in — panning a
            // not-yet-zoomed image has nothing to pan anyway, and
            // leaving it enabled unconditionally would let
            // InteractiveViewer's own drag recognizer win the gesture
            // arena over this page's swipe-to-dismiss handler above.
            panEnabled: _zoomed,
            minScale: 1,
            maxScale: 4,
            onInteractionEnd: _onInteraction,
            child: Center(
              child: SokoniNetworkImage(imageUrl: widget.imageUrl, fit: BoxFit.contain),
            ),
          ),
        ),
      ),
    );
  }
}
