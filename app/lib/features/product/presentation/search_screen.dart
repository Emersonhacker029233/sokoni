import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../shared/widgets/empty_state.dart';

class SearchScreen extends StatelessWidget {
  const SearchScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.navSearch)),
      body: SokoniEmptyState(
        icon: Icons.search_rounded,
        title: l10n.searchComingSoonTitle,
        message: l10n.searchComingSoonBody,
      ),
    );
  }
}
