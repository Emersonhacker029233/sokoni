import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'app_database.g.dart';

/// Small key/value store: language choice and the last good copy of the
/// driver profile, active job and earnings, so the app opens with real
/// content on a dead connection.
class KeyValues extends Table {
  TextColumn get key => text()();
  TextColumn get value => text()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {key};
}

/// Driver actions taken without a connection, replayed in order when the
/// network returns (docs/delivery/PLAN.md section 8). Only steps that need
/// no server-checked secret are queued: arrived at shop, on the way,
/// arrived, and call attempts. Pickup code and PIN need a live answer.
class OutboxEntries extends Table {
  IntColumn get id => integer().autoIncrement()();
  IntColumn get jobId => integer()();

  /// `status` or `call_attempt`.
  TextColumn get kind => text()();

  /// JSON body to POST.
  TextColumn get payload => text()();
  DateTimeColumn get createdAt => dateTime()();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  TextColumn get lastError => text().nullable()();
}

@DriftDatabase(tables: [KeyValues, OutboxEntries])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_open());
  AppDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 1;

  Future<String?> getValue(String key) async {
    final row = await (select(keyValues)..where((t) => t.key.equals(key))).getSingleOrNull();
    return row?.value;
  }

  Future<void> setValue(String key, String value) {
    return into(keyValues).insertOnConflictUpdate(
      KeyValuesCompanion.insert(key: key, value: value, updatedAt: DateTime.now()),
    );
  }

  Future<void> removeValue(String key) => (delete(keyValues)..where((t) => t.key.equals(key))).go();

  Future<int> enqueue({required int jobId, required String kind, required String payload}) {
    return into(outboxEntries).insert(
      OutboxEntriesCompanion.insert(jobId: jobId, kind: kind, payload: payload, createdAt: DateTime.now()),
    );
  }

  Future<List<OutboxEntry>> pendingOutbox() {
    return (select(outboxEntries)..orderBy([(t) => OrderingTerm.asc(t.id)])).get();
  }

  Stream<int> watchOutboxCount() {
    final count = outboxEntries.id.count();
    return (selectOnly(outboxEntries)..addColumns([count])).map((row) => row.read(count) ?? 0).watchSingle();
  }

  Future<void> removeOutboxEntry(int id) => (delete(outboxEntries)..where((t) => t.id.equals(id))).go();

  Future<void> markOutboxFailure(int id, String error) {
    return customUpdate(
      'UPDATE outbox_entries SET attempts = attempts + 1, last_error = ? WHERE id = ?',
      variables: [Variable.withString(error), Variable.withInt(id)],
      updates: {outboxEntries},
    );
  }

  /// Everything except the language choice, on sign-out.
  Future<void> clearAccountData() async {
    await delete(outboxEntries).go();
    await (delete(keyValues)..where((t) => t.key.equals(localeKey).not())).go();
  }

  static const localeKey = 'locale';
}

LazyDatabase _open() {
  return LazyDatabase(() async {
    final dir = await getApplicationDocumentsDirectory();
    return NativeDatabase.createInBackground(File(p.join(dir.path, 'sokoni_driver.sqlite')));
  });
}
