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
    final result = await ref.read(driverRepositoryProvider).me();
    return ProfileState(result.value, fromCache: result.fromCache);
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
