import 'dart:async';
import 'dart:convert';

import '../../core/network/api_exception.dart';
import '../../core/network/dio_client.dart';
import '../../core/storage/app_database.dart';
import 'driver_repository.dart';

/// Status steps that may be taken offline and sent later. Pickup and
/// delivery need the seller's code or the customer's PIN checked live.
const queueableStatuses = {'arrived_at_shop', 'on_the_way', 'arrived'};

/// Outcome of replaying one queued action.
class OutboxRejection {
  const OutboxRejection({required this.jobId, required this.kind, required this.message});

  final int jobId;
  final String kind;
  final String message;
}

/// Replays driver actions taken on a dead connection, oldest first. Stops
/// at the first connectivity failure so ordering is preserved. An action
/// the server refuses (for example the job was cancelled meanwhile) is
/// dropped and reported, never retried forever.
class Outbox {
  Outbox({required AppDatabase db, required DriverRepository repository})
      : _db = db,
        _repository = repository;

  final AppDatabase _db;
  final DriverRepository _repository;
  final _rejections = StreamController<OutboxRejection>.broadcast();
  Future<bool>? _running;

  Stream<OutboxRejection> get rejections => _rejections.stream;

  Stream<int> watchCount() => _db.watchOutboxCount();

  Future<void> queueStatus(int jobId, String status) {
    assert(queueableStatuses.contains(status), '$status needs a live connection');
    return _db.enqueue(jobId: jobId, kind: 'status', payload: jsonEncode({'status': status}));
  }

  Future<void> queueCallAttempt(int jobId) => _db.enqueue(jobId: jobId, kind: 'call_attempt', payload: '{}');

  /// Returns true when the outbox is empty afterwards. Overlapping calls
  /// (a reconnect and a tap at the same moment) share one run.
  Future<bool> flush() => _running ??= _flush().whenComplete(() => _running = null);

  Future<bool> _flush() async {
    for (final entry in await _db.pendingOutbox()) {
      try {
        if (entry.kind == 'status') {
          final status = (jsonDecode(entry.payload) as Map<String, dynamic>)['status'] as String;
          await _repository.updateStatus(entry.jobId, status);
        } else {
          await _repository.callAttempt(entry.jobId);
        }
        await _db.removeOutboxEntry(entry.id);
      } catch (e) {
        if (isConnectivityError(e)) {
          await _db.markOutboxFailure(entry.id, e.toString());
          return false;
        }
        // The server said no: this step can never succeed. Drop it and tell the UI.
        await _db.removeOutboxEntry(entry.id);
        _rejections.add(OutboxRejection(
          jobId: entry.jobId,
          kind: entry.kind,
          message: e is ApiException ? e.message : e.toString(),
        ));
      }
    }
    return true;
  }

  Future<bool> hasPendingFor(int jobId) async => (await _db.pendingOutbox()).any((e) => e.jobId == jobId);

  void dispose() => _rejections.close();
}
