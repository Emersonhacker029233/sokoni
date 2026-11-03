import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../data/models/driver_profile.dart';

/// The signed-in account's driver profile (null until they start
/// onboarding), and whether it is the saved offline copy.
class ProfileState {
  const ProfileState(this.profile, {this.fromCache = false});

  final DriverProfile? profile;
  final bool fromCache;
}

class ProfileController extends AsyncNotifier<ProfileState> {
  @override
  Future<ProfileState> build() async {
    if (ref.watch(authProvider) != AuthStatus.signedIn) return const ProfileState(null);
    _listenForAccountDecisions();
    final result = await ref.read(driverRepositoryProvider).me();
    return ProfileState(result.value, fromCache: result.fromCache);
  }

  /// Registers this phone for push as soon as the driver is signed in, not
  /// only once approved, so the approval or rejection itself can arrive as
  /// a push. A `driver_account` push re-reads the profile, which moves the
  /// router on (status screen to home, or to "needs changes").
  void _listenForAccountDecisions() {
    final push = ref.read(pushServiceProvider);
    final subscription = push.messages.listen((message) {
      if (message.type == 'driver_account') unawaited(refresh());
    });
    ref.onDispose(subscription.cancel);
    unawaited(push.start());
  }

  Future<void> refresh() async {
    final previous = state.value;
    try {
      final result = await ref.read(driverRepositoryProvider).me();
      state = AsyncData(ProfileState(result.value, fromCache: result.fromCache));
    } catch (e, st) {
      // Keep showing what we had; only an empty screen becomes an error.
      state = previous == null ? AsyncError(e, st) : AsyncData(previous);
    }
  }

  /// After onboarding calls that already return the fresh profile.
  void set(DriverProfile profile) => state = AsyncData(ProfileState(profile));
}

final profileProvider = AsyncNotifierProvider<ProfileController, ProfileState>(ProfileController.new);
