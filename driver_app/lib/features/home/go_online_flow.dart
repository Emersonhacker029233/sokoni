import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/location/location_tracker.dart';
import '../../core/providers.dart';
import '../../shared/widgets/common.dart';
import 'work_controller.dart';

/// Location permission with a plain-language explainer before every
/// system prompt (docs/delivery/PLAN.md section 8). "While using" first;
/// on iOS "Always" is asked for only here, when going online. Returns
/// true once the driver is online.
Future<bool> goOnlineFlow(BuildContext context, WidgetRef ref) async {
  final l = AppLocalizations.of(context);
  final tracker = ref.read(locationTrackerProvider);

  var access = await tracker.access();
  if (!context.mounted) return false;

  if (access == LocationAccess.serviceOff) {
    await _settingsDialog(context, l.locationServiceOff, tracker.openLocationSettings);
    return false;
  }
  if (access == LocationAccess.deniedForever) {
    await _settingsDialog(context, l.locationDenied, tracker.openSettings);
    return false;
  }
  if (access == LocationAccess.canAsk) {
    if (!await _explain(context, l.locationExplainerBody)) return false;
    access = await tracker.request();
    if (!context.mounted) return false;
    if (access != LocationAccess.granted) {
      if (access == LocationAccess.deniedForever) await _settingsDialog(context, l.locationDenied, tracker.openSettings);
      return false;
    }
  }

  // iOS suspends a "while using" app soon after it's backgrounded. Ask
  // once for "Always" with our own explanation; carry on either way.
  if (Platform.isIOS && !await tracker.hasAlways()) {
    if (!context.mounted) return false;
    if (await _explain(context, l.locationAlwaysExplainerBody)) await tracker.request();
  }

  try {
    await ref.read(workProvider.notifier).goOnline();
    return true;
  } catch (e) {
    if (context.mounted) showError(context, e);
    return false;
  }
}

Future<bool> _explain(BuildContext context, String body) async {
  final l = AppLocalizations.of(context);
  final ok = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      icon: const Icon(Icons.location_on_outlined, size: 32),
      title: Text(l.locationExplainerTitle),
      content: Text(body),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.notNow)),
        TextButton(onPressed: () => Navigator.pop(context, true), child: Text(l.allow)),
      ],
    ),
  );
  return ok == true;
}

Future<void> _settingsDialog(BuildContext context, String message, Future<void> Function() open) async {
  final l = AppLocalizations.of(context);
  final go = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      icon: const Icon(Icons.location_off_outlined, size: 32),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.cancel)),
        TextButton(onPressed: () => Navigator.pop(context, true), child: Text(l.openSettings)),
      ],
    ),
  );
  if (go == true) await open();
}
