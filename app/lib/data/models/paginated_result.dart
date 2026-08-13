/// Laravel's `paginate()` response shape (`{"data": [...], "meta": {...}}`),
/// parsed by hand rather than via a generic freezed class — json_serializable's
/// generic-argument-factories support adds ceremony that isn't worth it for
/// one call site per repository.
class PaginatedResult<T> {
  const PaginatedResult({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  factory PaginatedResult.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) fromJson) {
    final data = (json['data'] as List).cast<Map<String, dynamic>>();
    final meta = json['meta'] as Map<String, dynamic>?;
    return PaginatedResult(
      items: data.map(fromJson).toList(),
      currentPage: meta?['current_page'] as int? ?? 1,
      lastPage: meta?['last_page'] as int? ?? 1,
      total: meta?['total'] as int? ?? data.length,
    );
  }

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}
