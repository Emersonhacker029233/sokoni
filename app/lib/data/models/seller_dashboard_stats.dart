import 'package:freezed_annotation/freezed_annotation.dart';

part 'seller_dashboard_stats.freezed.dart';
part 'seller_dashboard_stats.g.dart';

/// Owner-only dashboard strip (CLAUDE.md Part 4) — mirrors
/// `SellerDashboardController`'s response shape. `totalViews` is a
/// lifetime total, not a 30-day figure — see that controller's docblock
/// for why there's no real 30-day-scoped view count to report.
@freezed
abstract class SellerDashboardStats with _$SellerDashboardStats {
  const factory SellerDashboardStats({
    @JsonKey(name: 'total_views') @Default(0) int totalViews,
    @JsonKey(name: 'saves_last_30_days') @Default(0) int savesLast30Days,
    @JsonKey(name: 'orders_last_30_days') @Default(0) int ordersLast30Days,
  }) = _SellerDashboardStats;

  factory SellerDashboardStats.fromJson(Map<String, dynamic> json) => _$SellerDashboardStatsFromJson(json);
}
