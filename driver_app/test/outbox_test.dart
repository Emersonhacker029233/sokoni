import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni_driver/core/network/api_exception.dart';
import 'package:sokoni_driver/core/storage/app_database.dart';
import 'package:sokoni_driver/data/api/driver_api.dart';
import 'package:sokoni_driver/data/models/driver_job.dart';
import 'package:sokoni_driver/data/repositories/driver_repository.dart';
import 'package:sokoni_driver/data/repositories/outbox.dart';

import 'fixtures.dart';

/// Records what the outbox sends and fails on demand.
class FakeRepository extends DriverRepository {
  FakeRepository(AppDatabase db) : super(api: DriverApi(Dio()), dio: Dio(), db: db);

  final sent = <String>[];
  Object? failWith;

  @override
  Future<DriverJob> updateStatus(int jobId, String status, {Map<String, dynamic> extra = const {}, String? proofPhotoPath}) async {
    if (failWith != null) throw failWith!;
    sent.add('$jobId:$status');
    return DriverJob.fromJson(jobJson(status: status));
  }

  @override
  Future<int> callAttempt(int jobId) async {
    if (failWith != null) throw failWith!;
    sent.add('$jobId:call');
    return 1;
  }
}

void main() {
  late AppDatabase db;
  late FakeRepository repo;
  late Outbox outbox;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    repo = FakeRepository(db);
    outbox = Outbox(db: db, repository: repo);
  });

  tearDown(() async {
    outbox.dispose();
    await db.close();
  });

  test('replays queued steps in the order they were taken', () async {
    await outbox.queueStatus(12, 'arrived_at_shop');
    await outbox.queueCallAttempt(12);

    expect(await outbox.flush(), isTrue);
    expect(repo.sent, ['12:arrived_at_shop', '12:call']);
    expect(await db.pendingOutbox(), isEmpty);
  });

  test('stops at the first connectivity failure and keeps everything', () async {
    await outbox.queueStatus(12, 'on_the_way');
    await outbox.queueStatus(12, 'arrived');
    repo.failWith = const NetworkException();

    expect(await outbox.flush(), isFalse);
    final pending = await db.pendingOutbox();
    expect(pending, hasLength(2));
    expect(pending.first.attempts, 1);

    repo.failWith = null;
    expect(await outbox.flush(), isTrue);
    expect(repo.sent, ['12:on_the_way', '12:arrived']);
  });

  test('a step the server refuses is dropped and reported, not retried forever', () async {
    await outbox.queueStatus(12, 'arrived');
    repo.failWith = const DeliveryActionException('This order was cancelled.', code: 'order_cancelled', statusCode: 409);

    final rejection = expectLater(outbox.rejections, emits(isA<OutboxRejection>().having((r) => r.message, 'message', 'This order was cancelled.')));
    expect(await outbox.flush(), isTrue);
    await rejection;
    expect(await db.pendingOutbox(), isEmpty);
  });

  test('only codeless steps are queueable', () {
    expect(queueableStatuses, containsAll(['arrived_at_shop', 'on_the_way', 'arrived']));
    expect(queueableStatuses, isNot(contains('picked_up')));
    expect(queueableStatuses, isNot(contains('delivered')));
  });

  test('cached active job survives a round trip and clears', () async {
    final job = DriverJob.fromJson(jobJson());
    await repo.cacheActiveJob(job);
    expect(await repo.cachedActiveJob(), job);
    await repo.cacheActiveJob(null);
    expect(await repo.cachedActiveJob(), isNull);
  });

  test('sign-out clears account data but keeps the language', () async {
    await db.setValue(AppDatabase.localeKey, 'en');
    await db.setValue('cache.driver_me', '{}');
    await outbox.queueStatus(1, 'arrived');

    await db.clearAccountData();

    expect(await db.getValue(AppDatabase.localeKey), 'en');
    expect(await db.getValue('cache.driver_me'), isNull);
    expect(await db.pendingOutbox(), isEmpty);
  });
}
