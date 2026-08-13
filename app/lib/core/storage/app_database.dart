import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'app_database.g.dart';

/// Cached API responses, keyed by resource id, stored as the raw JSON the
/// API returned. Repositories decode this into the app's model classes —
/// the cache layer itself stays schema-agnostic so it doesn't need to
/// change every time a resource gains a field.
class CachedProducts extends Table {
  IntColumn get id => integer()();
  IntColumn get categoryId => integer().nullable()();
  TextColumn get json => text()();
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

class CachedCategories extends Table {
  IntColumn get id => integer()();
  TextColumn get json => text()();
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

class CachedSellers extends Table {
  IntColumn get id => integer()();
  TextColumn get handle => text()();
  TextColumn get json => text()();
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Small key/value table for cached app state that isn't a resource list —
/// last known lat/lng (CLAUDE.md feature 1: "Cache last known position"),
/// last-used discovery filters, and similar.
class CachedKeyValues extends Table {
  TextColumn get key => text()();
  TextColumn get value => text()();

  @override
  Set<Column> get primaryKey => {key};
}

@DriftDatabase(tables: [CachedProducts, CachedCategories, CachedSellers, CachedKeyValues])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());
  AppDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 1;

  Future<void> cacheProducts(List<(int id, int? categoryId, String json)> rows) async {
    final now = DateTime.now();
    await batch((batch) {
      batch.insertAllOnConflictUpdate(
        cachedProducts,
        [
          for (final (id, categoryId, json) in rows)
            CachedProductsCompanion.insert(
              id: Value(id),
              categoryId: Value(categoryId),
              json: json,
              cachedAt: now,
            ),
        ],
      );
    });
  }

  Future<List<String>> readCachedProductsJson({int? categoryId, int limit = 40}) {
    final query = select(cachedProducts)
      ..orderBy([(t) => OrderingTerm.desc(t.cachedAt)])
      ..limit(limit);
    if (categoryId != null) {
      query.where((t) => t.categoryId.equals(categoryId));
    }
    return query.map((row) => row.json).get();
  }

  Future<void> setKeyValue(String key, String value) {
    return into(cachedKeyValues).insertOnConflictUpdate(
      CachedKeyValuesCompanion.insert(key: key, value: value),
    );
  }

  Future<String?> getKeyValue(String key) async {
    final row = await (select(
      cachedKeyValues,
    )..where((t) => t.key.equals(key))).getSingleOrNull();
    return row?.value;
  }

  /// Drops all cached rows — used on logout so one account's cache never
  /// leaks into another's feed.
  Future<void> clearAll() async {
    await batch((batch) {
      batch.deleteAll(cachedProducts);
      batch.deleteAll(cachedCategories);
      batch.deleteAll(cachedSellers);
    });
  }
}

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dbFolder = await getApplicationDocumentsDirectory();
    final file = File(p.join(dbFolder.path, 'sokoni_cache.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}
