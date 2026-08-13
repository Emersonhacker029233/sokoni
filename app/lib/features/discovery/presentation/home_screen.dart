import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../shared/widgets/connectivity_banner.dart';
import '../../../shared/widgets/empty_state.dart';

/// Home tab. The discovery feed (Near You / Trending / New Sellers,
/// category chips, map toggle) is Phase 4 — this is the Phase 3 shell
/// checkpoint: real navigation, real connectivity awareness, nothing fake.
class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.appName)),
      body: ConnectivityBanner(
        child: SokoniEmptyState(
          icon: Icons.storefront_outlined,
          title: l10n.homeComingSoonTitle,
          message: l10n.homeComingSoonBody,
        ),
      ),
    );
  }
}
