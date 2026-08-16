import 'package:flutter/material.dart';

/// Diagnostic-only entrypoint (see DECISIONS.md) — no providers, no
/// plugins, no router, no theme system. Just confirms Flutter's engine
/// and Dart runtime actually boot and paint a frame on a device where the
/// real app hangs before ever reaching this point.
///
/// Build with:
///   flutter build apk --flavor diagnostic --release -t lib/main_diagnostic.dart
///
/// Installs alongside the real app (separate applicationId, see
/// android/app/build.gradle.kts) rather than replacing it.
void main() {
  runApp(const _DiagnosticApp());
}

class _DiagnosticApp extends StatelessWidget {
  const _DiagnosticApp();

  @override
  Widget build(BuildContext context) {
    return const MaterialApp(
      debugShowCheckedModeBanner: false,
      home: Scaffold(
        backgroundColor: Colors.black,
        body: Center(
          child: Text(
            'Sokoni starting',
            style: TextStyle(color: Colors.white, fontSize: 24),
          ),
        ),
      ),
    );
  }
}
