import 'dart:async';
import 'dart:io';

import 'package:geolocator/geolocator.dart';

/// Where the permission flow stands.
enum LocationAccess {
  /// Granted (while-in-use is enough on Android thanks to the foreground service).
  granted,

  /// Not asked yet, or asked and refused once: the app may ask again.
  canAsk,

  /// Refused permanently: only the system settings can change it.
  deniedForever,

  /// The phone's GPS / location service is switched off.
  serviceOff,
}

/// One stream of positions shared by everything that needs it: the 30 s
/// server heartbeat, the 4 s live-tracking writes and the home map.
///
/// Runs only while the driver is online or on a job. On Android it is a
/// foreground service with a persistent notification, so it keeps going
/// when the driver switches to Google Maps. On iOS it uses the `location`
/// background mode with the blue status-bar indicator.
class LocationTracker {
  final _positions = StreamController<Position>.broadcast();
  StreamSubscription<Position>? _subscription;
  Position? _last;

  Stream<Position> get positions => _positions.stream;
  Position? get last => _last;
  bool get isRunning => _subscription != null;

  Future<LocationAccess> access() async {
    if (!await Geolocator.isLocationServiceEnabled()) return LocationAccess.serviceOff;
    return _map(await Geolocator.checkPermission());
  }

  /// Shows the system prompt. Callers show their own explainer first.
  Future<LocationAccess> request() async {
    if (!await Geolocator.isLocationServiceEnabled()) return LocationAccess.serviceOff;
    return _map(await Geolocator.requestPermission());
  }

  /// iOS only: whether "Always" was granted. When it wasn't, tracking
  /// still works while the app is open and for a short time after.
  Future<bool> hasAlways() async => await Geolocator.checkPermission() == LocationPermission.always;

  Future<void> openSettings() => Geolocator.openAppSettings();

  Future<void> openLocationSettings() => Geolocator.openLocationSettings();

  Future<Position?> current() async {
    try {
      final p = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 15)),
      );
      _last = p;
      return p;
    } catch (_) {
      return _last ?? await Geolocator.getLastKnownPosition();
    }
  }

  /// [notificationTitle]/[notificationText] are already localised by the caller.
  void start({required String notificationTitle, required String notificationText}) {
    if (_subscription != null) return;

    final LocationSettings settings;
    if (Platform.isAndroid) {
      settings = AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
        intervalDuration: const Duration(seconds: 4),
        foregroundNotificationConfig: ForegroundNotificationConfig(
          notificationTitle: notificationTitle,
          notificationText: notificationText,
          notificationIcon: const AndroidResource(name: 'ic_launcher', defType: 'mipmap'),
          enableWakeLock: true,
          setOngoing: true,
        ),
      );
    } else if (Platform.isIOS) {
      settings = AppleSettings(
        accuracy: LocationAccuracy.high,
        activityType: ActivityType.automotiveNavigation,
        distanceFilter: 10,
        pauseLocationUpdatesAutomatically: false,
        allowBackgroundLocationUpdates: true,
        showBackgroundLocationIndicator: true,
      );
    } else {
      settings = const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 10);
    }

    _subscription = Geolocator.getPositionStream(locationSettings: settings).listen(
      (p) {
        _last = p;
        _positions.add(p);
      },
      onError: (Object _) {
        // Permission revoked or GPS switched off mid-shift: the heartbeat
        // keeps sending the last fix and the UI shows the access state.
      },
    );
  }

  Future<void> stop() async {
    await _subscription?.cancel();
    _subscription = null;
  }

  LocationAccess _map(LocationPermission p) => switch (p) {
        LocationPermission.always || LocationPermission.whileInUse => LocationAccess.granted,
        LocationPermission.deniedForever => LocationAccess.deniedForever,
        _ => LocationAccess.canAsk,
      };

  void dispose() {
    unawaitedCancel();
    _positions.close();
  }

  void unawaitedCancel() {
    _subscription?.cancel();
    _subscription = null;
  }
}
