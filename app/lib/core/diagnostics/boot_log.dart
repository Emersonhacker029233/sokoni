import 'package:flutter/services.dart';

/// Best-effort startup-phase logging to the diagnostic build's native log
/// file (`Downloads/sokoni-boot.txt`, written by `DiagnosticMainActivity`
/// via MediaStore — see DECISIONS.md for the Android-16-device startup
/// hang investigation this exists for).
///
/// A silent no-op in the real app and on iOS: nothing registers this
/// channel outside the diagnostic Android flavor, so `invokeMethod` fails
/// with a `MissingPluginException` that's swallowed here rather than
/// surfaced — the same fails-soft shape as `PushService` around Firebase.
/// Safe to leave these calls in place rather than strip them once the
/// current investigation is resolved; the cost of a no-op failed method
/// channel call is negligible.
abstract final class BootLog {
  static const _channel = MethodChannel('sokoni.diagnostic/log');

  static void step(String message) {
    _channel.invokeMethod<void>('log', {'line': message}).catchError((_) {});
  }
}
