// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'earnings.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_EarningsReport _$EarningsReportFromJson(
  Map<String, dynamic> json,
) => _EarningsReport(
  summary: EarningsSummary.fromJson(json['summary'] as Map<String, dynamic>),
  balance: EarningsBalance.fromJson(json['balance'] as Map<String, dynamic>),
  payouts:
      (json['payouts'] as List<dynamic>?)
          ?.map((e) => Payout.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <Payout>[],
  commissionPct: (json['commission_pct'] as num?)?.toDouble() ?? 0,
);

Map<String, dynamic> _$EarningsReportToJson(_EarningsReport instance) =>
    <String, dynamic>{
      'summary': instance.summary.toJson(),
      'balance': instance.balance.toJson(),
      'payouts': instance.payouts.map((e) => e.toJson()).toList(),
      'commission_pct': instance.commissionPct,
    };

_EarningsSummary _$EarningsSummaryFromJson(Map<String, dynamic> json) =>
    _EarningsSummary(
      from: DateTime.parse(json['from'] as String),
      to: DateTime.parse(json['to'] as String),
      jobsCount: (json['jobs_count'] as num?)?.toInt() ?? 0,
      deliveredCount: (json['delivered_count'] as num?)?.toInt() ?? 0,
      grossFees: (json['gross_fees'] as num?)?.toInt() ?? 0,
      commission: (json['commission'] as num?)?.toInt() ?? 0,
      earnings: (json['earnings'] as num?)?.toInt() ?? 0,
      cashCollected: (json['cash_collected'] as num?)?.toInt() ?? 0,
      byDay:
          (json['by_day'] as List<dynamic>?)
              ?.map((e) => EarningsDay.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const <EarningsDay>[],
      jobs:
          (json['jobs'] as List<dynamic>?)
              ?.map((e) => EarningsJob.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const <EarningsJob>[],
    );

Map<String, dynamic> _$EarningsSummaryToJson(_EarningsSummary instance) =>
    <String, dynamic>{
      'from': instance.from.toIso8601String(),
      'to': instance.to.toIso8601String(),
      'jobs_count': instance.jobsCount,
      'delivered_count': instance.deliveredCount,
      'gross_fees': instance.grossFees,
      'commission': instance.commission,
      'earnings': instance.earnings,
      'cash_collected': instance.cashCollected,
      'by_day': instance.byDay.map((e) => e.toJson()).toList(),
      'jobs': instance.jobs.map((e) => e.toJson()).toList(),
    };

_EarningsDay _$EarningsDayFromJson(Map<String, dynamic> json) => _EarningsDay(
  date: json['date'] as String,
  jobs: (json['jobs'] as num?)?.toInt() ?? 0,
  earnings: (json['earnings'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$EarningsDayToJson(_EarningsDay instance) =>
    <String, dynamic>{
      'date': instance.date,
      'jobs': instance.jobs,
      'earnings': instance.earnings,
    };

_EarningsJob _$EarningsJobFromJson(Map<String, dynamic> json) => _EarningsJob(
  id: (json['id'] as num).toInt(),
  orderCode: json['order_code'] as String?,
  status: json['status'] as String,
  fee: (json['fee'] as num?)?.toInt() ?? 0,
  commission: (json['commission'] as num?)?.toInt() ?? 0,
  earnedAmount: (json['earned_amount'] as num?)?.toInt() ?? 0,
  paymentStatus: json['payment_status'] as String?,
  distanceKm: (json['distance_km'] as num?)?.toDouble(),
  settledAt: json['settled_at'] == null
      ? null
      : DateTime.parse(json['settled_at'] as String),
  paidOut: json['paid_out'] as bool? ?? false,
);

Map<String, dynamic> _$EarningsJobToJson(_EarningsJob instance) =>
    <String, dynamic>{
      'id': instance.id,
      'order_code': instance.orderCode,
      'status': instance.status,
      'fee': instance.fee,
      'commission': instance.commission,
      'earned_amount': instance.earnedAmount,
      'payment_status': instance.paymentStatus,
      'distance_km': instance.distanceKm,
      'settled_at': instance.settledAt?.toIso8601String(),
      'paid_out': instance.paidOut,
    };

_EarningsBalance _$EarningsBalanceFromJson(Map<String, dynamic> json) =>
    _EarningsBalance(
      unpaidEarnings: (json['unpaid_earnings'] as num?)?.toInt() ?? 0,
      cashHeld: (json['cash_held'] as num?)?.toInt() ?? 0,
      balance: (json['balance'] as num?)?.toInt() ?? 0,
    );

Map<String, dynamic> _$EarningsBalanceToJson(_EarningsBalance instance) =>
    <String, dynamic>{
      'unpaid_earnings': instance.unpaidEarnings,
      'cash_held': instance.cashHeld,
      'balance': instance.balance,
    };

_Payout _$PayoutFromJson(Map<String, dynamic> json) => _Payout(
  id: (json['id'] as num).toInt(),
  amount: (json['amount'] as num?)?.toInt() ?? 0,
  status: json['status'] as String,
  method: json['method'] as String?,
  reference: json['reference'] as String?,
  periodStart: json['period_start'] as String?,
  periodEnd: json['period_end'] as String?,
  paidAt: json['paid_at'] == null
      ? null
      : DateTime.parse(json['paid_at'] as String),
);

Map<String, dynamic> _$PayoutToJson(_Payout instance) => <String, dynamic>{
  'id': instance.id,
  'amount': instance.amount,
  'status': instance.status,
  'method': instance.method,
  'reference': instance.reference,
  'period_start': instance.periodStart,
  'period_end': instance.periodEnd,
  'paid_at': instance.paidAt?.toIso8601String(),
};
