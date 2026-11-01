import 'package:freezed_annotation/freezed_annotation.dart';

part 'driver_profile.freezed.dart';
part 'driver_profile.g.dart';

/// Mirrors `DriverProfileResource`. Never carries the full NIDA number or
/// document images, only whether each was uploaded.
@freezed
abstract class DriverProfile with _$DriverProfile {
  const DriverProfile._();

  const factory DriverProfile({
    required int id,
    required String name,
    String? phone,

    /// `pending`, `approved`, `rejected`, `suspended`.
    required String status,
    @JsonKey(name: 'submitted_at') DateTime? submittedAt,
    @JsonKey(name: 'rejection_reason') String? rejectionReason,
    @JsonKey(name: 'suspended_reason') String? suspendedReason,
    @JsonKey(name: 'is_online') @Default(false) bool isOnline,
    @JsonKey(name: 'rating_avg') double? ratingAvg,
    @JsonKey(name: 'rating_count') @Default(0) int ratingCount,
    @JsonKey(name: 'jobs_completed') @Default(0) int jobsCompleted,
    @JsonKey(name: 'photo_url') String? photoUrl,
    @JsonKey(name: 'national_id_last4') String? nationalIdLast4,
    @JsonKey(name: 'licence_number') String? licenceNumber,
    @JsonKey(name: 'licence_expires_on') String? licenceExpiresOn,
    @JsonKey(name: 'licence_expired') @Default(false) bool licenceExpired,
    @Default(DriverDocuments()) DriverDocuments documents,
    DriverVehicle? vehicle,
    @JsonKey(name: 'missing_requirements') @Default(<String>[]) List<String> missingRequirements,
    @JsonKey(name: 'can_submit') @Default(false) bool canSubmit,
    @JsonKey(name: 'can_go_online') @Default(false) bool canGoOnline,
    @JsonKey(name: 'active_job_id') int? activeJobId,
    @JsonKey(name: 'terms_accepted_at') DateTime? termsAcceptedAt,
  }) = _DriverProfile;

  factory DriverProfile.fromJson(Map<String, dynamic> json) => _$DriverProfileFromJson(json);

  bool get isApproved => status == 'approved';
  bool get isSuspended => status == 'suspended';
  bool get isRejected => status == 'rejected';

  /// Created but never sent for review: still onboarding.
  bool get isDraft => submittedAt == null && status != 'approved';

  /// Sent and waiting on a human.
  bool get isUnderReview => status == 'pending' && submittedAt != null;
}

@freezed
abstract class DriverDocuments with _$DriverDocuments {
  const factory DriverDocuments({
    @Default(false) bool photo,
    @JsonKey(name: 'national_id_image') @Default(false) bool nationalIdImage,
    @JsonKey(name: 'licence_image') @Default(false) bool licenceImage,
  }) = _DriverDocuments;

  factory DriverDocuments.fromJson(Map<String, dynamic> json) => _$DriverDocumentsFromJson(json);
}

@freezed
abstract class DriverVehicle with _$DriverVehicle {
  const factory DriverVehicle({
    /// `boda_boda`, `bajaj`, `car`, `bicycle`.
    required String type,
    @JsonKey(name: 'plate_number') String? plateNumber,
    String? make,
    String? colour,
    @Default(false) bool verified,
  }) = _DriverVehicle;

  factory DriverVehicle.fromJson(Map<String, dynamic> json) => _$DriverVehicleFromJson(json);
}
