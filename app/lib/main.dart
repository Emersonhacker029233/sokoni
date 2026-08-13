import 'package:flutter/material.dart';

import 'core/l10n/gen/app_localizations.dart';
import 'core/motion/motion.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/dimens.dart';
import 'features/debug/motion_gallery_screen.dart';

void main() {
  runApp(const SokoniApp());
}

class SokoniApp extends StatelessWidget {
  const SokoniApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      debugShowCheckedModeBanner: false,
      theme: SokoniTheme.light,
      darkTheme: SokoniTheme.dark,
      themeMode: ThemeMode.system,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: const _AppEntry(),
      routes: {'/motion-gallery': (context) => const MotionGalleryScreen()},
    );
  }
}

/// Temporary app entry point ahead of Phase 3's go_router shell + tab
/// navigation: plays the splash primitive once, then lands on a minimal
/// placeholder home with a way into the motion gallery.
class _AppEntry extends StatefulWidget {
  const _AppEntry();

  @override
  State<_AppEntry> createState() => _AppEntryState();
}

class _AppEntryState extends State<_AppEntry> {
  bool _splashDone = false;

  @override
  Widget build(BuildContext context) {
    if (!_splashDone) {
      return SokoniSplashTransition(
        onComplete: () => setState(() => _splashDone = true),
      );
    }
    return const _PlaceholderHome();
  }
}

class _PlaceholderHome extends StatelessWidget {
  const _PlaceholderHome();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.appName)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(SokoniDimens.space24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.shopping_bag_rounded, size: 56),
              const SizedBox(height: SokoniDimens.space16),
              Text(
                l10n.appName,
                style: Theme.of(context).textTheme.displayLarge,
              ),
              const SizedBox(height: SokoniDimens.space8),
              Text(
                'App shell lands in Phase 3 — this is the Phase 1 design-system checkpoint.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: SokoniDimens.space24),
              FilledButton(
                onPressed: () => Navigator.of(context).pushNamed('/motion-gallery'),
                child: Text(l10n.motionGalleryTitle),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
