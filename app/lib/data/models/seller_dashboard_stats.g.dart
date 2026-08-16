// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'seller_dashboard_stats.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SellerDashboardStats _$SellerDashboardStatsFromJson(
  Map<String, dynamic> json,
) => _SellerDashboardStats(
  totalViews: (json['total_views'] as num?)?.toInt() ?? 0,
  savesLast30Days: (json['saves_last_30_days'] as num?)?.toInt() ?? 0,
  ordersLast30Days: (json['orders_last_30_days'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$SellerDashboardStatsToJson(
  _SellerDashboardStats instance,
) => <String, dynamic>{
  'total_views': instance.totalViews,
  'saves_last_30_days': instance.savesLast30Days,
  'orders_last_30_days': instance.ordersLast30Days,
};
