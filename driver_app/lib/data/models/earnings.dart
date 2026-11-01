import 'package:freezed_annotation/freezed_annotation.dart';

part 'earnings.freezed.dart';
part 'earnings.g.dart';

/// `GET /driver/earnings` (`DriverEarnings` on the API). Whole TZS.
@freezed
abstract class EarningsReport with _$EarningsReport {
  const factory EarningsReport({
    required EarningsSummary summary,
    required EarningsBalance balance,
    @Default(<Payout>[]) List<Payout> payouts,
    @JsonKey(name: 'commission_pct') @Default(0) double commissionPct,
  }) = _EarningsReport;

  factory EarningsReport.fromJson(Map<String, dynamic> json) => _$EarningsReportFromJson(json);
}

@freezed
abstract class EarningsSummary with _$EarningsSummary {
  const factory EarningsSummary({
    required DateTime from,
    required DateTime to,
    @JsonKey(name: 'jobs_count') @Default(0) int jobsCount,
    @JsonKey(name: 'delivered_count') @Default(0) int deliveredCount,
    @JsonKey(name: 'gross_fees') @Default(0) int grossFees,
    @Default(0) int commission,
    @Default(0) int earnings,
    @JsonKey(name: 'cash_collected') @Default(0) int cashCollected,
    @JsonKey(name: 'by_day') @Default(<EarningsDay>[]) List<EarningsDay> byDay,
    @Default(<EarningsJob>[]) List<EarningsJob> jobs,
  }) = _EarningsSummary;

  factory EarningsSummary.fromJson(Map<String, dynamic> json) => _$EarningsSummaryFromJson(json);
}

@freezed
abstract class EarningsDay with _$EarningsDay {
  const factory EarningsDay({
    required String date,
    @Default(0) int jobs,
    @Default(0) int earnings,
  }) = _EarningsDay;

  factory EarningsDay.fromJson(Map<String, dynamic> json) => _$EarningsDayFromJson(json);
}

@freezed
abstract class EarningsJob with _$EarningsJob {
  const factory EarningsJob({
    required int id,
    @JsonKey(name: 'order_code') String? orderCode,
    required String status,
    @Default(0) int fee,
    @Default(0) int commission,
    @JsonKey(name: 'earned_amount') @Default(0) int earnedAmount,
    @JsonKey(name: 'payment_status') String? paymentStatus,
    @JsonKey(name: 'distance_km') double? distanceKm,
    @JsonKey(name: 'settled_at') DateTime? settledAt,
    @JsonKey(name: 'paid_out') @Default(false) bool paidOut,
  }) = _EarningsJob;

  factory EarningsJob.fromJson(Map<String, dynamic> json) => _$EarningsJobFromJson(json);
}

@freezed
abstract class EarningsBalance with _$EarningsBalance {
  const factory EarningsBalance({
    @JsonKey(name: 'unpaid_earnings') @Default(0) int unpaidEarnings,
    @JsonKey(name: 'cash_held') @Default(0) int cashHeld,
    @Default(0) int balance,
  }) = _EarningsBalance;

  factory EarningsBalance.fromJson(Map<String, dynamic> json) => _$EarningsBalanceFromJson(json);
}

@freezed
abstract class Payout with _$Payout {
  const factory Payout({
    required int id,
    @Default(0) int amount,
    required String status,
    String? method,
    String? reference,
    @JsonKey(name: 'period_start') String? periodStart,
    @JsonKey(name: 'period_end') String? periodEnd,
    @JsonKey(name: 'paid_at') DateTime? paidAt,
  }) = _Payout;

  factory Payout.fromJson(Map<String, dynamic> json) => _$PayoutFromJson(json);
}
