import 'dart:developer' as developer;

import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_database/firebase_database.dart';
import 'package:geolocator/geolocator.dart';

import '../../data/repositories/driver_repository.dart';
import '../push/firebase_bootstrap.dart';

/// Streams the driver's position to Firebase Realtime Database at
/// `/tracking/{jobId}/loc` every few seconds while a job is active
/// (docs/delivery/PLAN.md section 2). Laravel opens and deletes the node
/// and the security rules only accept writes from this job's driver.
///
/// When Firebase or the server-side credentials are missing, [start]
/// returns false and the caller sends the server heartbeat more often
/// instead, which the buyer app polls.
class LiveTracking {
  LiveTracking({required DriverRepository repository}) : _repository = repository;

  final DriverRepository _repository;

  static const minInterval = Duration(seconds: 4);

  DatabaseReference? _ref;
  int? _jobId;
  DateTime? _lastWrite;

  bool get isLive => _ref != null;
  int? get jobId => _jobId;

  Future<bool> start(int jobId) async {
    if (_jobId == jobId && _ref != null) return true;
    await stop();
    if (!await FirebaseBootstrap.ensure()) return false;

    try {
      final credentials = await _repository.realtimeToken();
      if (credentials == null) return false;

      await FirebaseAuth.instance.signInWithCustomToken('${credentials['token']}');
      final database = FirebaseDatabase.instanceFor(app: Firebase.app(), databaseURL: '${credentials['database_url']}');
      _ref = database.ref('tracking/$jobId/loc');
      _jobId = jobId;
      return true;
    } catch (e) {
      developer.log('Live tracking unavailable; using the server heartbeat.', name: 'Tracking', error: e);
      _ref = null;
      _jobId = null;
      return false;
    }
  }

  Future<void> push(Position p) async {
    final ref = _ref;
    if (ref == null) return;
    final now = DateTime.now();
    if (_lastWrite != null && now.difference(_lastWrite!) < minInterval) return;
    _lastWrite = now;
    try {
      await ref.set({
        'lat': p.latitude,
        'lng': p.longitude,
        if (p.heading >= 0) 'heading': p.heading.round(),
        'ts': ServerValue.timestamp,
      });
    } catch (e) {
      // Node already deleted (job ended) or token expired: drop out and let
      // the next job start fresh.
      developer.log('Live tracking write failed.', name: 'Tracking', error: e);
      await stop();
    }
  }

  Future<void> stop() async {
    _ref = null;
    _jobId = null;
    _lastWrite = null;
    try {
      if (Firebase.apps.isNotEmpty && FirebaseAuth.instance.currentUser != null) {
        await FirebaseAuth.instance.signOut();
      }
    } catch (_) {}
  }
}
