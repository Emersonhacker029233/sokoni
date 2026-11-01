import 'package:freezed_annotation/freezed_annotation.dart';

part 'driver_job.freezed.dart';
part 'driver_job.g.dart';

/// Mirrors the driver view of `DeliveryJobResource`.
@freezed
abstract class DriverJob with _$DriverJob {
  const DriverJob._();

  const factory DriverJob({
    required int id,
    @JsonKey(name: 'order_id') required int orderId,

    /// Masked (`SKAB12••••`) until pickup: its last 4 characters are the
    /// pickup code the seller gives the driver.
    @JsonKey(name: 'order_code') required String orderCode,
    required String status,
    @JsonKey(name: 'is_active') @Default(false) bool isActive,
    @JsonKey(name: 'distance_km') double? distanceKm,
    @Default(<TimelineEntry>[]) List<TimelineEntry> timeline,
    @JsonKey(name: 'cancelled_by') String? cancelledBy,
    @JsonKey(name: 'cancel_reason') String? cancelReason,
    @JsonKey(name: 'failure_reason') String? failureReason,
    @JsonKey(name: 'created_at') DateTime? createdAt,
    @Default(0) int fee,
    @Default(0) int commission,
    @JsonKey(name: 'driver_earnings') @Default(0) int driverEarnings,
    @JsonKey(name: 'earned_amount') int? earnedAmount,
    @JsonKey(name: 'payment_status') String? paymentStatus,
    @JsonKey(name: 'collect_cash') @Default(0) int collectCash,
    required JobPlace shop,
    required JobPlace customer,
    @Default(<JobItem>[]) List<JobItem> items,
    String? notes,
    @JsonKey(name: 'call_attempts') @Default(0) int callAttempts,
    @JsonKey(name: 'pin_attempts_left') @Default(5) int pinAttemptsLeft,
    @JsonKey(name: 'pin_locked') @Default(false) bool pinLocked,
    @JsonKey(name: 'proof_photo_required') @Default(false) bool proofPhotoRequired,
    @JsonKey(name: 'unreachable_available_at') DateTime? unreachableAvailableAt,
    @Default(JobActions()) JobActions actions,
  }) = _DriverJob;

  factory DriverJob.fromJson(Map<String, dynamic> json) => _$DriverJobFromJson(json);

  bool get isFinished => const ['delivered', 'cancelled', 'failed'].contains(status);

  bool get beforePickup => status == 'driver_assigned' || status == 'arrived_at_shop';

  int get itemsCount => items.fold(0, (sum, i) => sum + i.qty);
}

@freezed
abstract class JobPlace with _$JobPlace {
  const factory JobPlace({
    String? name,
    String? address,
    String? phone,
    required double lat,
    required double lng,
  }) = _JobPlace;

  factory JobPlace.fromJson(Map<String, dynamic> json) => _$JobPlaceFromJson(json);
}

@freezed
abstract class JobItem with _$JobItem {
  const factory JobItem({required String title, @Default(1) int qty}) = _JobItem;

  factory JobItem.fromJson(Map<String, dynamic> json) => _$JobItemFromJson(json);
}

@freezed
abstract class TimelineEntry with _$TimelineEntry {
  const factory TimelineEntry({
    String? status,
    DateTime? at,
    String? by,
    String? reason,
  }) = _TimelineEntry;

  factory TimelineEntry.fromJson(Map<String, dynamic> json) => _$TimelineEntryFromJson(json);
}

@freezed
abstract class JobActions with _$JobActions {
  const factory JobActions({
    @Default(<String>[]) List<String> next,
    @JsonKey(name: 'can_cancel') @Default(false) bool canCancel,
    @JsonKey(name: 'can_rate') @Default(false) bool canRate,
  }) = _JobActions;

  factory JobActions.fromJson(Map<String, dynamic> json) => _$JobActionsFromJson(json);
}
