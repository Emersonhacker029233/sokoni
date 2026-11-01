// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'driver_profile.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_DriverProfile _$DriverProfileFromJson(Map<String, dynamic> json) =>
    _DriverProfile(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      phone: json['phone'] as String?,
      status: json['status'] as String,
      submittedAt: json['submitted_at'] == null
          ? null
          : DateTime.parse(json['submitted_at'] as String),
      rejectionReason: json['rejection_reason'] as String?,
      suspendedReason: json['suspended_reason'] as String?,
      isOnline: json['is_online'] as bool? ?? false,
      ratingAvg: (json['rating_avg'] as num?)?.toDouble(),
      ratingCount: (json['rating_count'] as num?)?.toInt() ?? 0,
      jobsCompleted: (json['jobs_completed'] as num?)?.toInt() ?? 0,
      photoUrl: json['photo_url'] as String?,
      nationalIdLast4: json['national_id_last4'] as String?,
      licenceNumber: json['licence_number'] as String?,
      licenceExpiresOn: json['licence_expires_on'] as String?,
      licenceExpired: json['licence_expired'] as bool? ?? false,
      documents: json['documents'] == null
          ? const DriverDocuments()
          : DriverDocuments.fromJson(json['documents'] as Map<String, dynamic>),
      vehicle: json['vehicle'] == null
          ? null
          : DriverVehicle.fromJson(json['vehicle'] as Map<String, dynamic>),
      missingRequirements:
          (json['missing_requirements'] as List<dynamic>?)
              ?.map((e) => e as String)
              .toList() ??
          const <String>[],
      canSubmit: json['can_submit'] as bool? ?? false,
      canGoOnline: json['can_go_online'] as bool? ?? false,
      activeJobId: (json['active_job_id'] as num?)?.toInt(),
      termsAcceptedAt: json['terms_accepted_at'] == null
          ? null
          : DateTime.parse(json['terms_accepted_at'] as String),
    );

Map<String, dynamic> _$DriverProfileToJson(_DriverProfile instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'phone': instance.phone,
      'status': instance.status,
      'submitted_at': instance.submittedAt?.toIso8601String(),
      'rejection_reason': instance.rejectionReason,
      'suspended_reason': instance.suspendedReason,
      'is_online': instance.isOnline,
      'rating_avg': instance.ratingAvg,
      'rating_count': instance.ratingCount,
      'jobs_completed': instance.jobsCompleted,
      'photo_url': instance.photoUrl,
      'national_id_last4': instance.nationalIdLast4,
      'licence_number': instance.licenceNumber,
      'licence_expires_on': instance.licenceExpiresOn,
      'licence_expired': instance.licenceExpired,
      'documents': instance.documents.toJson(),
      'vehicle': instance.vehicle?.toJson(),
      'missing_requirements': instance.missingRequirements,
      'can_submit': instance.canSubmit,
      'can_go_online': instance.canGoOnline,
      'active_job_id': instance.activeJobId,
      'terms_accepted_at': instance.termsAcceptedAt?.toIso8601String(),
    };

_DriverDocuments _$DriverDocumentsFromJson(Map<String, dynamic> json) =>
    _DriverDocuments(
      photo: json['photo'] as bool? ?? false,
      nationalIdImage: json['national_id_image'] as bool? ?? false,
      licenceImage: json['licence_image'] as bool? ?? false,
    );

Map<String, dynamic> _$DriverDocumentsToJson(_DriverDocuments instance) =>
    <String, dynamic>{
      'photo': instance.photo,
      'national_id_image': instance.nationalIdImage,
      'licence_image': instance.licenceImage,
    };

_DriverVehicle _$DriverVehicleFromJson(Map<String, dynamic> json) =>
    _DriverVehicle(
      type: json['type'] as String,
      plateNumber: json['plate_number'] as String?,
      make: json['make'] as String?,
      colour: json['colour'] as String?,
      verified: json['verified'] as bool? ?? false,
    );

Map<String, dynamic> _$DriverVehicleToJson(_DriverVehicle instance) =>
    <String, dynamic>{
      'type': instance.type,
      'plate_number': instance.plateNumber,
      'make': instance.make,
      'colour': instance.colour,
      'verified': instance.verified,
    };
