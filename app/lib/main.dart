import 'dart:async';
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/diagnostics/boot_log.dart';
import 'core/l10n/gen/app_localizations.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';

Timer? _globalStartupWatchdog;
bool _firstFrameConfirmed = false;

void main() {
  // Everything below runs inside a guarded zone, with both Flutter's own
  // widget-build error hook and the root isolate's uncaught-error hook
  // wired to BootLog — see DECISIONS.md. A silent black screen with no
  // BootLog output at all almost always means an uncaught exception
  // during the very first build, before any of this app's own error UI
  // exists to show it; this exists to name that exception instead of
  // guessing.
  runZonedGuarded(() {
    WidgetsFlutterBinding.ensureInitialized();
    // The true first line: platform channels need a binding to send
    // anything at all, so this is the earliest point a BootLog call can
    // possibly succeed — see BootLog and DiagnosticMainActivity's
    // handling of this exact message.
    BootLog.step('main() entered — binding initialized, Dart is running');

    FlutterError.onError = (FlutterErrorDetails details) {
      BootLog.step('FlutterError.onError: ${details.exceptionAsString()}');
      BootLog.step('FlutterError stack: ${details.stack}');
      FlutterError.presentError(details);
    };

    PlatformDispatcher.instance.onError = (Object error, StackTrace stack) {
      BootLog.step('PlatformDispatcher.onError: $error');
      BootLog.step('PlatformDispatcher stack: $stack');
      return true;
    };

    // Flutter's default in-memory image cache (1000 entries / 100MB) is sized
    // for a generic app, not a mid-range-Android-on-3G target (CLAUDE.md).
    // Product photos are already served at three real, purpose-sized
    // variants (thumb/card/full — see ProductMediaController server-side),
    // so this cap is just a ceiling against unbounded growth while browsing
    // a long feed, not a substitute for requesting the right size.
    PaintingBinding.instance.imageCache.maximumSizeBytes = 60 << 20; // 60MB
    PaintingBinding.instance.imageCache.maximumSize = 200;

    // Armed here, before anything else runs — not inside a widget that may
    // never build in the first place (see SplashScreen's own, separate 5s
    // watchdog in core/router/splash_screen.dart, which only helps once a
    // widget tree exists to run it). This one exists specifically to catch
    // a hang *before* Flutter ever paints a first frame at all — see
    // DECISIONS.md for the startup-hang investigation this is part of.
    _armGlobalStartupWatchdog();

    BootLog.step('about to runApp()');
    runApp(const ProviderScope(child: SokoniApp()));

    WidgetsBinding.instance.addPostFrameCallback((_) => _confirmFirstFrame());
  }, (Object error, StackTrace stack) {
    BootLog.step('runZonedGuarded caught: $error');
    BootLog.step('runZonedGuarded stack: $stack');
  });
}

void _armGlobalStartupWatchdog() {
  BootLog.step('global watchdog armed');
  _globalStartupWatchdog = Timer(const Duration(seconds: 5), () {
    if (_firstFrameConfirmed) return;
    BootLog.step('GLOBAL WATCHDOG FIRED — no first frame within 5s');
    runApp(const _StartupTimeoutApp());
  });
}

void _confirmFirstFrame() {
  if (_firstFrameConfirmed) return;
  _firstFrameConfirmed = true;
  _globalStartupWatchdog?.cancel();
  BootLog.step('first frame confirmed — global watchdog cancelled');
}

class SokoniApp extends ConsumerWidget {
  const SokoniApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    BootLog.step('SokoniApp.build() start');
    final router = ref.watch(routerProvider);
    BootLog.step('SokoniApp.build() router obtained');

    return MaterialApp.router(
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      debugShowCheckedModeBanner: false,
      theme: SokoniTheme.light,
      darkTheme: SokoniTheme.dark,
      themeMode: ThemeMode.system,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: router,
    );
  }
}

/// Last-resort fallback if [_globalStartupWatchdog] fires — nothing
/// painted a first frame within 5 seconds of `runApp()`. Deliberately a
/// separate, minimal `MaterialApp` rather than anything from the real
/// widget tree, since whatever's wrong might be in that tree's own setup.
class _StartupTimeoutApp extends StatelessWidget {
  const _StartupTimeoutApp();

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: Builder(
        builder: (context) {
          final l10n = AppLocalizations.of(context);
          return Scaffold(
            backgroundColor: Colors.black,
            body: SafeArea(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.error_outline_rounded, color: Colors.white, size: 40),
                      const SizedBox(height: 16),
                      Text(
                        l10n.startupTimeoutBody,
                        style: const TextStyle(color: Colors.white),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 20),
                      FilledButton(
                        onPressed: () {
                          _armGlobalStartupWatchdog();
                          runApp(const ProviderScope(child: SokoniApp()));
                          WidgetsBinding.instance.addPostFrameCallback((_) => _confirmFirstFrame());
                        },
                        child: Text(l10n.commonRetry),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
