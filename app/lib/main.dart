import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/l10n/gen/app_localizations.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';

void main() {
  // Flutter's default in-memory image cache (1000 entries / 100MB) is sized
  // for a generic app, not a mid-range-Android-on-3G target (CLAUDE.md).
  // Product photos are already served at three real, purpose-sized
  // variants (thumb/card/full — see ProductMediaController server-side),
  // so this cap is just a ceiling against unbounded growth while browsing
  // a long feed, not a substitute for requesting the right size.
  PaintingBinding.instance.imageCache.maximumSizeBytes = 60 << 20; // 60MB
  PaintingBinding.instance.imageCache.maximumSize = 200;

  runApp(const ProviderScope(child: SokoniApp()));
}

class SokoniApp extends ConsumerWidget {
  const SokoniApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

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
