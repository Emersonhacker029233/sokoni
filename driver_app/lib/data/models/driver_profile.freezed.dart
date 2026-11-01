// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'driver_profile.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$DriverProfile {

 int get id; String get name; String? get phone;/// `pending`, `approved`, `rejected`, `suspended`.
 String get status;@JsonKey(name: 'submitted_at') DateTime? get submittedAt;@JsonKey(name: 'rejection_reason') String? get rejectionReason;@JsonKey(name: 'suspended_reason') String? get suspendedReason;@JsonKey(name: 'is_online') bool get isOnline;@JsonKey(name: 'rating_avg') double? get ratingAvg;@JsonKey(name: 'rating_count') int get ratingCount;@JsonKey(name: 'jobs_completed') int get jobsCompleted;@JsonKey(name: 'photo_url') String? get photoUrl;@JsonKey(name: 'national_id_last4') String? get nationalIdLast4;@JsonKey(name: 'licence_number') String? get licenceNumber;@JsonKey(name: 'licence_expires_on') String? get licenceExpiresOn;@JsonKey(name: 'licence_expired') bool get licenceExpired; DriverDocuments get documents; DriverVehicle? get vehicle;@JsonKey(name: 'missing_requirements') List<String> get missingRequirements;@JsonKey(name: 'can_submit') bool get canSubmit;@JsonKey(name: 'can_go_online') bool get canGoOnline;@JsonKey(name: 'active_job_id') int? get activeJobId;@JsonKey(name: 'terms_accepted_at') DateTime? get termsAcceptedAt;
/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DriverProfileCopyWith<DriverProfile> get copyWith => _$DriverProfileCopyWithImpl<DriverProfile>(this as DriverProfile, _$identity);

  /// Serializes this DriverProfile to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DriverProfile&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.status, status) || other.status == status)&&(identical(other.submittedAt, submittedAt) || other.submittedAt == submittedAt)&&(identical(other.rejectionReason, rejectionReason) || other.rejectionReason == rejectionReason)&&(identical(other.suspendedReason, suspendedReason) || other.suspendedReason == suspendedReason)&&(identical(other.isOnline, isOnline) || other.isOnline == isOnline)&&(identical(other.ratingAvg, ratingAvg) || other.ratingAvg == ratingAvg)&&(identical(other.ratingCount, ratingCount) || other.ratingCount == ratingCount)&&(identical(other.jobsCompleted, jobsCompleted) || other.jobsCompleted == jobsCompleted)&&(identical(other.photoUrl, photoUrl) || other.photoUrl == photoUrl)&&(identical(other.nationalIdLast4, nationalIdLast4) || other.nationalIdLast4 == nationalIdLast4)&&(identical(other.licenceNumber, licenceNumber) || other.licenceNumber == licenceNumber)&&(identical(other.licenceExpiresOn, licenceExpiresOn) || other.licenceExpiresOn == licenceExpiresOn)&&(identical(other.licenceExpired, licenceExpired) || other.licenceExpired == licenceExpired)&&(identical(other.documents, documents) || other.documents == documents)&&(identical(other.vehicle, vehicle) || other.vehicle == vehicle)&&const DeepCollectionEquality().equals(other.missingRequirements, missingRequirements)&&(identical(other.canSubmit, canSubmit) || other.canSubmit == canSubmit)&&(identical(other.canGoOnline, canGoOnline) || other.canGoOnline == canGoOnline)&&(identical(other.activeJobId, activeJobId) || other.activeJobId == activeJobId)&&(identical(other.termsAcceptedAt, termsAcceptedAt) || other.termsAcceptedAt == termsAcceptedAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,name,phone,status,submittedAt,rejectionReason,suspendedReason,isOnline,ratingAvg,ratingCount,jobsCompleted,photoUrl,nationalIdLast4,licenceNumber,licenceExpiresOn,licenceExpired,documents,vehicle,const DeepCollectionEquality().hash(missingRequirements),canSubmit,canGoOnline,activeJobId,termsAcceptedAt]);

@override
String toString() {
  return 'DriverProfile(id: $id, name: $name, phone: $phone, status: $status, submittedAt: $submittedAt, rejectionReason: $rejectionReason, suspendedReason: $suspendedReason, isOnline: $isOnline, ratingAvg: $ratingAvg, ratingCount: $ratingCount, jobsCompleted: $jobsCompleted, photoUrl: $photoUrl, nationalIdLast4: $nationalIdLast4, licenceNumber: $licenceNumber, licenceExpiresOn: $licenceExpiresOn, licenceExpired: $licenceExpired, documents: $documents, vehicle: $vehicle, missingRequirements: $missingRequirements, canSubmit: $canSubmit, canGoOnline: $canGoOnline, activeJobId: $activeJobId, termsAcceptedAt: $termsAcceptedAt)';
}


}

/// @nodoc
abstract mixin class $DriverProfileCopyWith<$Res>  {
  factory $DriverProfileCopyWith(DriverProfile value, $Res Function(DriverProfile) _then) = _$DriverProfileCopyWithImpl;
@useResult
$Res call({
 int id, String name, String? phone, String status,@JsonKey(name: 'submitted_at') DateTime? submittedAt,@JsonKey(name: 'rejection_reason') String? rejectionReason,@JsonKey(name: 'suspended_reason') String? suspendedReason,@JsonKey(name: 'is_online') bool isOnline,@JsonKey(name: 'rating_avg') double? ratingAvg,@JsonKey(name: 'rating_count') int ratingCount,@JsonKey(name: 'jobs_completed') int jobsCompleted,@JsonKey(name: 'photo_url') String? photoUrl,@JsonKey(name: 'national_id_last4') String? nationalIdLast4,@JsonKey(name: 'licence_number') String? licenceNumber,@JsonKey(name: 'licence_expires_on') String? licenceExpiresOn,@JsonKey(name: 'licence_expired') bool licenceExpired, DriverDocuments documents, DriverVehicle? vehicle,@JsonKey(name: 'missing_requirements') List<String> missingRequirements,@JsonKey(name: 'can_submit') bool canSubmit,@JsonKey(name: 'can_go_online') bool canGoOnline,@JsonKey(name: 'active_job_id') int? activeJobId,@JsonKey(name: 'terms_accepted_at') DateTime? termsAcceptedAt
});


$DriverDocumentsCopyWith<$Res> get documents;$DriverVehicleCopyWith<$Res>? get vehicle;

}
/// @nodoc
class _$DriverProfileCopyWithImpl<$Res>
    implements $DriverProfileCopyWith<$Res> {
  _$DriverProfileCopyWithImpl(this._self, this._then);

  final DriverProfile _self;
  final $Res Function(DriverProfile) _then;

/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? name = null,Object? phone = freezed,Object? status = null,Object? submittedAt = freezed,Object? rejectionReason = freezed,Object? suspendedReason = freezed,Object? isOnline = null,Object? ratingAvg = freezed,Object? ratingCount = null,Object? jobsCompleted = null,Object? photoUrl = freezed,Object? nationalIdLast4 = freezed,Object? licenceNumber = freezed,Object? licenceExpiresOn = freezed,Object? licenceExpired = null,Object? documents = null,Object? vehicle = freezed,Object? missingRequirements = null,Object? canSubmit = null,Object? canGoOnline = null,Object? activeJobId = freezed,Object? termsAcceptedAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,submittedAt: freezed == submittedAt ? _self.submittedAt : submittedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,rejectionReason: freezed == rejectionReason ? _self.rejectionReason : rejectionReason // ignore: cast_nullable_to_non_nullable
as String?,suspendedReason: freezed == suspendedReason ? _self.suspendedReason : suspendedReason // ignore: cast_nullable_to_non_nullable
as String?,isOnline: null == isOnline ? _self.isOnline : isOnline // ignore: cast_nullable_to_non_nullable
as bool,ratingAvg: freezed == ratingAvg ? _self.ratingAvg : ratingAvg // ignore: cast_nullable_to_non_nullable
as double?,ratingCount: null == ratingCount ? _self.ratingCount : ratingCount // ignore: cast_nullable_to_non_nullable
as int,jobsCompleted: null == jobsCompleted ? _self.jobsCompleted : jobsCompleted // ignore: cast_nullable_to_non_nullable
as int,photoUrl: freezed == photoUrl ? _self.photoUrl : photoUrl // ignore: cast_nullable_to_non_nullable
as String?,nationalIdLast4: freezed == nationalIdLast4 ? _self.nationalIdLast4 : nationalIdLast4 // ignore: cast_nullable_to_non_nullable
as String?,licenceNumber: freezed == licenceNumber ? _self.licenceNumber : licenceNumber // ignore: cast_nullable_to_non_nullable
as String?,licenceExpiresOn: freezed == licenceExpiresOn ? _self.licenceExpiresOn : licenceExpiresOn // ignore: cast_nullable_to_non_nullable
as String?,licenceExpired: null == licenceExpired ? _self.licenceExpired : licenceExpired // ignore: cast_nullable_to_non_nullable
as bool,documents: null == documents ? _self.documents : documents // ignore: cast_nullable_to_non_nullable
as DriverDocuments,vehicle: freezed == vehicle ? _self.vehicle : vehicle // ignore: cast_nullable_to_non_nullable
as DriverVehicle?,missingRequirements: null == missingRequirements ? _self.missingRequirements : missingRequirements // ignore: cast_nullable_to_non_nullable
as List<String>,canSubmit: null == canSubmit ? _self.canSubmit : canSubmit // ignore: cast_nullable_to_non_nullable
as bool,canGoOnline: null == canGoOnline ? _self.canGoOnline : canGoOnline // ignore: cast_nullable_to_non_nullable
as bool,activeJobId: freezed == activeJobId ? _self.activeJobId : activeJobId // ignore: cast_nullable_to_non_nullable
as int?,termsAcceptedAt: freezed == termsAcceptedAt ? _self.termsAcceptedAt : termsAcceptedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DriverDocumentsCopyWith<$Res> get documents {
  
  return $DriverDocumentsCopyWith<$Res>(_self.documents, (value) {
    return _then(_self.copyWith(documents: value));
  });
}/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DriverVehicleCopyWith<$Res>? get vehicle {
    if (_self.vehicle == null) {
    return null;
  }

  return $DriverVehicleCopyWith<$Res>(_self.vehicle!, (value) {
    return _then(_self.copyWith(vehicle: value));
  });
}
}


/// Adds pattern-matching-related methods to [DriverProfile].
extension DriverProfilePatterns on DriverProfile {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DriverProfile value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DriverProfile() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DriverProfile value)  $default,){
final _that = this;
switch (_that) {
case _DriverProfile():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DriverProfile value)?  $default,){
final _that = this;
switch (_that) {
case _DriverProfile() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String name,  String? phone,  String status, @JsonKey(name: 'submitted_at')  DateTime? submittedAt, @JsonKey(name: 'rejection_reason')  String? rejectionReason, @JsonKey(name: 'suspended_reason')  String? suspendedReason, @JsonKey(name: 'is_online')  bool isOnline, @JsonKey(name: 'rating_avg')  double? ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount, @JsonKey(name: 'jobs_completed')  int jobsCompleted, @JsonKey(name: 'photo_url')  String? photoUrl, @JsonKey(name: 'national_id_last4')  String? nationalIdLast4, @JsonKey(name: 'licence_number')  String? licenceNumber, @JsonKey(name: 'licence_expires_on')  String? licenceExpiresOn, @JsonKey(name: 'licence_expired')  bool licenceExpired,  DriverDocuments documents,  DriverVehicle? vehicle, @JsonKey(name: 'missing_requirements')  List<String> missingRequirements, @JsonKey(name: 'can_submit')  bool canSubmit, @JsonKey(name: 'can_go_online')  bool canGoOnline, @JsonKey(name: 'active_job_id')  int? activeJobId, @JsonKey(name: 'terms_accepted_at')  DateTime? termsAcceptedAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DriverProfile() when $default != null:
return $default(_that.id,_that.name,_that.phone,_that.status,_that.submittedAt,_that.rejectionReason,_that.suspendedReason,_that.isOnline,_that.ratingAvg,_that.ratingCount,_that.jobsCompleted,_that.photoUrl,_that.nationalIdLast4,_that.licenceNumber,_that.licenceExpiresOn,_that.licenceExpired,_that.documents,_that.vehicle,_that.missingRequirements,_that.canSubmit,_that.canGoOnline,_that.activeJobId,_that.termsAcceptedAt);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String name,  String? phone,  String status, @JsonKey(name: 'submitted_at')  DateTime? submittedAt, @JsonKey(name: 'rejection_reason')  String? rejectionReason, @JsonKey(name: 'suspended_reason')  String? suspendedReason, @JsonKey(name: 'is_online')  bool isOnline, @JsonKey(name: 'rating_avg')  double? ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount, @JsonKey(name: 'jobs_completed')  int jobsCompleted, @JsonKey(name: 'photo_url')  String? photoUrl, @JsonKey(name: 'national_id_last4')  String? nationalIdLast4, @JsonKey(name: 'licence_number')  String? licenceNumber, @JsonKey(name: 'licence_expires_on')  String? licenceExpiresOn, @JsonKey(name: 'licence_expired')  bool licenceExpired,  DriverDocuments documents,  DriverVehicle? vehicle, @JsonKey(name: 'missing_requirements')  List<String> missingRequirements, @JsonKey(name: 'can_submit')  bool canSubmit, @JsonKey(name: 'can_go_online')  bool canGoOnline, @JsonKey(name: 'active_job_id')  int? activeJobId, @JsonKey(name: 'terms_accepted_at')  DateTime? termsAcceptedAt)  $default,) {final _that = this;
switch (_that) {
case _DriverProfile():
return $default(_that.id,_that.name,_that.phone,_that.status,_that.submittedAt,_that.rejectionReason,_that.suspendedReason,_that.isOnline,_that.ratingAvg,_that.ratingCount,_that.jobsCompleted,_that.photoUrl,_that.nationalIdLast4,_that.licenceNumber,_that.licenceExpiresOn,_that.licenceExpired,_that.documents,_that.vehicle,_that.missingRequirements,_that.canSubmit,_that.canGoOnline,_that.activeJobId,_that.termsAcceptedAt);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String name,  String? phone,  String status, @JsonKey(name: 'submitted_at')  DateTime? submittedAt, @JsonKey(name: 'rejection_reason')  String? rejectionReason, @JsonKey(name: 'suspended_reason')  String? suspendedReason, @JsonKey(name: 'is_online')  bool isOnline, @JsonKey(name: 'rating_avg')  double? ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount, @JsonKey(name: 'jobs_completed')  int jobsCompleted, @JsonKey(name: 'photo_url')  String? photoUrl, @JsonKey(name: 'national_id_last4')  String? nationalIdLast4, @JsonKey(name: 'licence_number')  String? licenceNumber, @JsonKey(name: 'licence_expires_on')  String? licenceExpiresOn, @JsonKey(name: 'licence_expired')  bool licenceExpired,  DriverDocuments documents,  DriverVehicle? vehicle, @JsonKey(name: 'missing_requirements')  List<String> missingRequirements, @JsonKey(name: 'can_submit')  bool canSubmit, @JsonKey(name: 'can_go_online')  bool canGoOnline, @JsonKey(name: 'active_job_id')  int? activeJobId, @JsonKey(name: 'terms_accepted_at')  DateTime? termsAcceptedAt)?  $default,) {final _that = this;
switch (_that) {
case _DriverProfile() when $default != null:
return $default(_that.id,_that.name,_that.phone,_that.status,_that.submittedAt,_that.rejectionReason,_that.suspendedReason,_that.isOnline,_that.ratingAvg,_that.ratingCount,_that.jobsCompleted,_that.photoUrl,_that.nationalIdLast4,_that.licenceNumber,_that.licenceExpiresOn,_that.licenceExpired,_that.documents,_that.vehicle,_that.missingRequirements,_that.canSubmit,_that.canGoOnline,_that.activeJobId,_that.termsAcceptedAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DriverProfile extends DriverProfile {
  const _DriverProfile({required this.id, required this.name, this.phone, required this.status, @JsonKey(name: 'submitted_at') this.submittedAt, @JsonKey(name: 'rejection_reason') this.rejectionReason, @JsonKey(name: 'suspended_reason') this.suspendedReason, @JsonKey(name: 'is_online') this.isOnline = false, @JsonKey(name: 'rating_avg') this.ratingAvg, @JsonKey(name: 'rating_count') this.ratingCount = 0, @JsonKey(name: 'jobs_completed') this.jobsCompleted = 0, @JsonKey(name: 'photo_url') this.photoUrl, @JsonKey(name: 'national_id_last4') this.nationalIdLast4, @JsonKey(name: 'licence_number') this.licenceNumber, @JsonKey(name: 'licence_expires_on') this.licenceExpiresOn, @JsonKey(name: 'licence_expired') this.licenceExpired = false, this.documents = const DriverDocuments(), this.vehicle, @JsonKey(name: 'missing_requirements') final  List<String> missingRequirements = const <String>[], @JsonKey(name: 'can_submit') this.canSubmit = false, @JsonKey(name: 'can_go_online') this.canGoOnline = false, @JsonKey(name: 'active_job_id') this.activeJobId, @JsonKey(name: 'terms_accepted_at') this.termsAcceptedAt}): _missingRequirements = missingRequirements,super._();
  factory _DriverProfile.fromJson(Map<String, dynamic> json) => _$DriverProfileFromJson(json);

@override final  int id;
@override final  String name;
@override final  String? phone;
/// `pending`, `approved`, `rejected`, `suspended`.
@override final  String status;
@override@JsonKey(name: 'submitted_at') final  DateTime? submittedAt;
@override@JsonKey(name: 'rejection_reason') final  String? rejectionReason;
@override@JsonKey(name: 'suspended_reason') final  String? suspendedReason;
@override@JsonKey(name: 'is_online') final  bool isOnline;
@override@JsonKey(name: 'rating_avg') final  double? ratingAvg;
@override@JsonKey(name: 'rating_count') final  int ratingCount;
@override@JsonKey(name: 'jobs_completed') final  int jobsCompleted;
@override@JsonKey(name: 'photo_url') final  String? photoUrl;
@override@JsonKey(name: 'national_id_last4') final  String? nationalIdLast4;
@override@JsonKey(name: 'licence_number') final  String? licenceNumber;
@override@JsonKey(name: 'licence_expires_on') final  String? licenceExpiresOn;
@override@JsonKey(name: 'licence_expired') final  bool licenceExpired;
@override@JsonKey() final  DriverDocuments documents;
@override final  DriverVehicle? vehicle;
 final  List<String> _missingRequirements;
@override@JsonKey(name: 'missing_requirements') List<String> get missingRequirements {
  if (_missingRequirements is EqualUnmodifiableListView) return _missingRequirements;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_missingRequirements);
}

@override@JsonKey(name: 'can_submit') final  bool canSubmit;
@override@JsonKey(name: 'can_go_online') final  bool canGoOnline;
@override@JsonKey(name: 'active_job_id') final  int? activeJobId;
@override@JsonKey(name: 'terms_accepted_at') final  DateTime? termsAcceptedAt;

/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DriverProfileCopyWith<_DriverProfile> get copyWith => __$DriverProfileCopyWithImpl<_DriverProfile>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DriverProfileToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DriverProfile&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.status, status) || other.status == status)&&(identical(other.submittedAt, submittedAt) || other.submittedAt == submittedAt)&&(identical(other.rejectionReason, rejectionReason) || other.rejectionReason == rejectionReason)&&(identical(other.suspendedReason, suspendedReason) || other.suspendedReason == suspendedReason)&&(identical(other.isOnline, isOnline) || other.isOnline == isOnline)&&(identical(other.ratingAvg, ratingAvg) || other.ratingAvg == ratingAvg)&&(identical(other.ratingCount, ratingCount) || other.ratingCount == ratingCount)&&(identical(other.jobsCompleted, jobsCompleted) || other.jobsCompleted == jobsCompleted)&&(identical(other.photoUrl, photoUrl) || other.photoUrl == photoUrl)&&(identical(other.nationalIdLast4, nationalIdLast4) || other.nationalIdLast4 == nationalIdLast4)&&(identical(other.licenceNumber, licenceNumber) || other.licenceNumber == licenceNumber)&&(identical(other.licenceExpiresOn, licenceExpiresOn) || other.licenceExpiresOn == licenceExpiresOn)&&(identical(other.licenceExpired, licenceExpired) || other.licenceExpired == licenceExpired)&&(identical(other.documents, documents) || other.documents == documents)&&(identical(other.vehicle, vehicle) || other.vehicle == vehicle)&&const DeepCollectionEquality().equals(other._missingRequirements, _missingRequirements)&&(identical(other.canSubmit, canSubmit) || other.canSubmit == canSubmit)&&(identical(other.canGoOnline, canGoOnline) || other.canGoOnline == canGoOnline)&&(identical(other.activeJobId, activeJobId) || other.activeJobId == activeJobId)&&(identical(other.termsAcceptedAt, termsAcceptedAt) || other.termsAcceptedAt == termsAcceptedAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,name,phone,status,submittedAt,rejectionReason,suspendedReason,isOnline,ratingAvg,ratingCount,jobsCompleted,photoUrl,nationalIdLast4,licenceNumber,licenceExpiresOn,licenceExpired,documents,vehicle,const DeepCollectionEquality().hash(_missingRequirements),canSubmit,canGoOnline,activeJobId,termsAcceptedAt]);

@override
String toString() {
  return 'DriverProfile(id: $id, name: $name, phone: $phone, status: $status, submittedAt: $submittedAt, rejectionReason: $rejectionReason, suspendedReason: $suspendedReason, isOnline: $isOnline, ratingAvg: $ratingAvg, ratingCount: $ratingCount, jobsCompleted: $jobsCompleted, photoUrl: $photoUrl, nationalIdLast4: $nationalIdLast4, licenceNumber: $licenceNumber, licenceExpiresOn: $licenceExpiresOn, licenceExpired: $licenceExpired, documents: $documents, vehicle: $vehicle, missingRequirements: $missingRequirements, canSubmit: $canSubmit, canGoOnline: $canGoOnline, activeJobId: $activeJobId, termsAcceptedAt: $termsAcceptedAt)';
}


}

/// @nodoc
abstract mixin class _$DriverProfileCopyWith<$Res> implements $DriverProfileCopyWith<$Res> {
  factory _$DriverProfileCopyWith(_DriverProfile value, $Res Function(_DriverProfile) _then) = __$DriverProfileCopyWithImpl;
@override @useResult
$Res call({
 int id, String name, String? phone, String status,@JsonKey(name: 'submitted_at') DateTime? submittedAt,@JsonKey(name: 'rejection_reason') String? rejectionReason,@JsonKey(name: 'suspended_reason') String? suspendedReason,@JsonKey(name: 'is_online') bool isOnline,@JsonKey(name: 'rating_avg') double? ratingAvg,@JsonKey(name: 'rating_count') int ratingCount,@JsonKey(name: 'jobs_completed') int jobsCompleted,@JsonKey(name: 'photo_url') String? photoUrl,@JsonKey(name: 'national_id_last4') String? nationalIdLast4,@JsonKey(name: 'licence_number') String? licenceNumber,@JsonKey(name: 'licence_expires_on') String? licenceExpiresOn,@JsonKey(name: 'licence_expired') bool licenceExpired, DriverDocuments documents, DriverVehicle? vehicle,@JsonKey(name: 'missing_requirements') List<String> missingRequirements,@JsonKey(name: 'can_submit') bool canSubmit,@JsonKey(name: 'can_go_online') bool canGoOnline,@JsonKey(name: 'active_job_id') int? activeJobId,@JsonKey(name: 'terms_accepted_at') DateTime? termsAcceptedAt
});


@override $DriverDocumentsCopyWith<$Res> get documents;@override $DriverVehicleCopyWith<$Res>? get vehicle;

}
/// @nodoc
class __$DriverProfileCopyWithImpl<$Res>
    implements _$DriverProfileCopyWith<$Res> {
  __$DriverProfileCopyWithImpl(this._self, this._then);

  final _DriverProfile _self;
  final $Res Function(_DriverProfile) _then;

/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? name = null,Object? phone = freezed,Object? status = null,Object? submittedAt = freezed,Object? rejectionReason = freezed,Object? suspendedReason = freezed,Object? isOnline = null,Object? ratingAvg = freezed,Object? ratingCount = null,Object? jobsCompleted = null,Object? photoUrl = freezed,Object? nationalIdLast4 = freezed,Object? licenceNumber = freezed,Object? licenceExpiresOn = freezed,Object? licenceExpired = null,Object? documents = null,Object? vehicle = freezed,Object? missingRequirements = null,Object? canSubmit = null,Object? canGoOnline = null,Object? activeJobId = freezed,Object? termsAcceptedAt = freezed,}) {
  return _then(_DriverProfile(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,submittedAt: freezed == submittedAt ? _self.submittedAt : submittedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,rejectionReason: freezed == rejectionReason ? _self.rejectionReason : rejectionReason // ignore: cast_nullable_to_non_nullable
as String?,suspendedReason: freezed == suspendedReason ? _self.suspendedReason : suspendedReason // ignore: cast_nullable_to_non_nullable
as String?,isOnline: null == isOnline ? _self.isOnline : isOnline // ignore: cast_nullable_to_non_nullable
as bool,ratingAvg: freezed == ratingAvg ? _self.ratingAvg : ratingAvg // ignore: cast_nullable_to_non_nullable
as double?,ratingCount: null == ratingCount ? _self.ratingCount : ratingCount // ignore: cast_nullable_to_non_nullable
as int,jobsCompleted: null == jobsCompleted ? _self.jobsCompleted : jobsCompleted // ignore: cast_nullable_to_non_nullable
as int,photoUrl: freezed == photoUrl ? _self.photoUrl : photoUrl // ignore: cast_nullable_to_non_nullable
as String?,nationalIdLast4: freezed == nationalIdLast4 ? _self.nationalIdLast4 : nationalIdLast4 // ignore: cast_nullable_to_non_nullable
as String?,licenceNumber: freezed == licenceNumber ? _self.licenceNumber : licenceNumber // ignore: cast_nullable_to_non_nullable
as String?,licenceExpiresOn: freezed == licenceExpiresOn ? _self.licenceExpiresOn : licenceExpiresOn // ignore: cast_nullable_to_non_nullable
as String?,licenceExpired: null == licenceExpired ? _self.licenceExpired : licenceExpired // ignore: cast_nullable_to_non_nullable
as bool,documents: null == documents ? _self.documents : documents // ignore: cast_nullable_to_non_nullable
as DriverDocuments,vehicle: freezed == vehicle ? _self.vehicle : vehicle // ignore: cast_nullable_to_non_nullable
as DriverVehicle?,missingRequirements: null == missingRequirements ? _self._missingRequirements : missingRequirements // ignore: cast_nullable_to_non_nullable
as List<String>,canSubmit: null == canSubmit ? _self.canSubmit : canSubmit // ignore: cast_nullable_to_non_nullable
as bool,canGoOnline: null == canGoOnline ? _self.canGoOnline : canGoOnline // ignore: cast_nullable_to_non_nullable
as bool,activeJobId: freezed == activeJobId ? _self.activeJobId : activeJobId // ignore: cast_nullable_to_non_nullable
as int?,termsAcceptedAt: freezed == termsAcceptedAt ? _self.termsAcceptedAt : termsAcceptedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DriverDocumentsCopyWith<$Res> get documents {
  
  return $DriverDocumentsCopyWith<$Res>(_self.documents, (value) {
    return _then(_self.copyWith(documents: value));
  });
}/// Create a copy of DriverProfile
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DriverVehicleCopyWith<$Res>? get vehicle {
    if (_self.vehicle == null) {
    return null;
  }

  return $DriverVehicleCopyWith<$Res>(_self.vehicle!, (value) {
    return _then(_self.copyWith(vehicle: value));
  });
}
}


/// @nodoc
mixin _$DriverDocuments {

 bool get photo;@JsonKey(name: 'national_id_image') bool get nationalIdImage;@JsonKey(name: 'licence_image') bool get licenceImage;
/// Create a copy of DriverDocuments
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DriverDocumentsCopyWith<DriverDocuments> get copyWith => _$DriverDocumentsCopyWithImpl<DriverDocuments>(this as DriverDocuments, _$identity);

  /// Serializes this DriverDocuments to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DriverDocuments&&(identical(other.photo, photo) || other.photo == photo)&&(identical(other.nationalIdImage, nationalIdImage) || other.nationalIdImage == nationalIdImage)&&(identical(other.licenceImage, licenceImage) || other.licenceImage == licenceImage));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,photo,nationalIdImage,licenceImage);

@override
String toString() {
  return 'DriverDocuments(photo: $photo, nationalIdImage: $nationalIdImage, licenceImage: $licenceImage)';
}


}

/// @nodoc
abstract mixin class $DriverDocumentsCopyWith<$Res>  {
  factory $DriverDocumentsCopyWith(DriverDocuments value, $Res Function(DriverDocuments) _then) = _$DriverDocumentsCopyWithImpl;
@useResult
$Res call({
 bool photo,@JsonKey(name: 'national_id_image') bool nationalIdImage,@JsonKey(name: 'licence_image') bool licenceImage
});




}
/// @nodoc
class _$DriverDocumentsCopyWithImpl<$Res>
    implements $DriverDocumentsCopyWith<$Res> {
  _$DriverDocumentsCopyWithImpl(this._self, this._then);

  final DriverDocuments _self;
  final $Res Function(DriverDocuments) _then;

/// Create a copy of DriverDocuments
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? photo = null,Object? nationalIdImage = null,Object? licenceImage = null,}) {
  return _then(_self.copyWith(
photo: null == photo ? _self.photo : photo // ignore: cast_nullable_to_non_nullable
as bool,nationalIdImage: null == nationalIdImage ? _self.nationalIdImage : nationalIdImage // ignore: cast_nullable_to_non_nullable
as bool,licenceImage: null == licenceImage ? _self.licenceImage : licenceImage // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [DriverDocuments].
extension DriverDocumentsPatterns on DriverDocuments {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DriverDocuments value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DriverDocuments() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DriverDocuments value)  $default,){
final _that = this;
switch (_that) {
case _DriverDocuments():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DriverDocuments value)?  $default,){
final _that = this;
switch (_that) {
case _DriverDocuments() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( bool photo, @JsonKey(name: 'national_id_image')  bool nationalIdImage, @JsonKey(name: 'licence_image')  bool licenceImage)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DriverDocuments() when $default != null:
return $default(_that.photo,_that.nationalIdImage,_that.licenceImage);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( bool photo, @JsonKey(name: 'national_id_image')  bool nationalIdImage, @JsonKey(name: 'licence_image')  bool licenceImage)  $default,) {final _that = this;
switch (_that) {
case _DriverDocuments():
return $default(_that.photo,_that.nationalIdImage,_that.licenceImage);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( bool photo, @JsonKey(name: 'national_id_image')  bool nationalIdImage, @JsonKey(name: 'licence_image')  bool licenceImage)?  $default,) {final _that = this;
switch (_that) {
case _DriverDocuments() when $default != null:
return $default(_that.photo,_that.nationalIdImage,_that.licenceImage);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DriverDocuments implements DriverDocuments {
  const _DriverDocuments({this.photo = false, @JsonKey(name: 'national_id_image') this.nationalIdImage = false, @JsonKey(name: 'licence_image') this.licenceImage = false});
  factory _DriverDocuments.fromJson(Map<String, dynamic> json) => _$DriverDocumentsFromJson(json);

@override@JsonKey() final  bool photo;
@override@JsonKey(name: 'national_id_image') final  bool nationalIdImage;
@override@JsonKey(name: 'licence_image') final  bool licenceImage;

/// Create a copy of DriverDocuments
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DriverDocumentsCopyWith<_DriverDocuments> get copyWith => __$DriverDocumentsCopyWithImpl<_DriverDocuments>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DriverDocumentsToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DriverDocuments&&(identical(other.photo, photo) || other.photo == photo)&&(identical(other.nationalIdImage, nationalIdImage) || other.nationalIdImage == nationalIdImage)&&(identical(other.licenceImage, licenceImage) || other.licenceImage == licenceImage));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,photo,nationalIdImage,licenceImage);

@override
String toString() {
  return 'DriverDocuments(photo: $photo, nationalIdImage: $nationalIdImage, licenceImage: $licenceImage)';
}


}

/// @nodoc
abstract mixin class _$DriverDocumentsCopyWith<$Res> implements $DriverDocumentsCopyWith<$Res> {
  factory _$DriverDocumentsCopyWith(_DriverDocuments value, $Res Function(_DriverDocuments) _then) = __$DriverDocumentsCopyWithImpl;
@override @useResult
$Res call({
 bool photo,@JsonKey(name: 'national_id_image') bool nationalIdImage,@JsonKey(name: 'licence_image') bool licenceImage
});




}
/// @nodoc
class __$DriverDocumentsCopyWithImpl<$Res>
    implements _$DriverDocumentsCopyWith<$Res> {
  __$DriverDocumentsCopyWithImpl(this._self, this._then);

  final _DriverDocuments _self;
  final $Res Function(_DriverDocuments) _then;

/// Create a copy of DriverDocuments
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? photo = null,Object? nationalIdImage = null,Object? licenceImage = null,}) {
  return _then(_DriverDocuments(
photo: null == photo ? _self.photo : photo // ignore: cast_nullable_to_non_nullable
as bool,nationalIdImage: null == nationalIdImage ? _self.nationalIdImage : nationalIdImage // ignore: cast_nullable_to_non_nullable
as bool,licenceImage: null == licenceImage ? _self.licenceImage : licenceImage // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$DriverVehicle {

/// `boda_boda`, `bajaj`, `car`, `bicycle`.
 String get type;@JsonKey(name: 'plate_number') String? get plateNumber; String? get make; String? get colour; bool get verified;
/// Create a copy of DriverVehicle
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DriverVehicleCopyWith<DriverVehicle> get copyWith => _$DriverVehicleCopyWithImpl<DriverVehicle>(this as DriverVehicle, _$identity);

  /// Serializes this DriverVehicle to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DriverVehicle&&(identical(other.type, type) || other.type == type)&&(identical(other.plateNumber, plateNumber) || other.plateNumber == plateNumber)&&(identical(other.make, make) || other.make == make)&&(identical(other.colour, colour) || other.colour == colour)&&(identical(other.verified, verified) || other.verified == verified));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,type,plateNumber,make,colour,verified);

@override
String toString() {
  return 'DriverVehicle(type: $type, plateNumber: $plateNumber, make: $make, colour: $colour, verified: $verified)';
}


}

/// @nodoc
abstract mixin class $DriverVehicleCopyWith<$Res>  {
  factory $DriverVehicleCopyWith(DriverVehicle value, $Res Function(DriverVehicle) _then) = _$DriverVehicleCopyWithImpl;
@useResult
$Res call({
 String type,@JsonKey(name: 'plate_number') String? plateNumber, String? make, String? colour, bool verified
});




}
/// @nodoc
class _$DriverVehicleCopyWithImpl<$Res>
    implements $DriverVehicleCopyWith<$Res> {
  _$DriverVehicleCopyWithImpl(this._self, this._then);

  final DriverVehicle _self;
  final $Res Function(DriverVehicle) _then;

/// Create a copy of DriverVehicle
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? type = null,Object? plateNumber = freezed,Object? make = freezed,Object? colour = freezed,Object? verified = null,}) {
  return _then(_self.copyWith(
type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as String,plateNumber: freezed == plateNumber ? _self.plateNumber : plateNumber // ignore: cast_nullable_to_non_nullable
as String?,make: freezed == make ? _self.make : make // ignore: cast_nullable_to_non_nullable
as String?,colour: freezed == colour ? _self.colour : colour // ignore: cast_nullable_to_non_nullable
as String?,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [DriverVehicle].
extension DriverVehiclePatterns on DriverVehicle {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DriverVehicle value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DriverVehicle() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DriverVehicle value)  $default,){
final _that = this;
switch (_that) {
case _DriverVehicle():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DriverVehicle value)?  $default,){
final _that = this;
switch (_that) {
case _DriverVehicle() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String type, @JsonKey(name: 'plate_number')  String? plateNumber,  String? make,  String? colour,  bool verified)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DriverVehicle() when $default != null:
return $default(_that.type,_that.plateNumber,_that.make,_that.colour,_that.verified);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String type, @JsonKey(name: 'plate_number')  String? plateNumber,  String? make,  String? colour,  bool verified)  $default,) {final _that = this;
switch (_that) {
case _DriverVehicle():
return $default(_that.type,_that.plateNumber,_that.make,_that.colour,_that.verified);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String type, @JsonKey(name: 'plate_number')  String? plateNumber,  String? make,  String? colour,  bool verified)?  $default,) {final _that = this;
switch (_that) {
case _DriverVehicle() when $default != null:
return $default(_that.type,_that.plateNumber,_that.make,_that.colour,_that.verified);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DriverVehicle implements DriverVehicle {
  const _DriverVehicle({required this.type, @JsonKey(name: 'plate_number') this.plateNumber, this.make, this.colour, this.verified = false});
  factory _DriverVehicle.fromJson(Map<String, dynamic> json) => _$DriverVehicleFromJson(json);

/// `boda_boda`, `bajaj`, `car`, `bicycle`.
@override final  String type;
@override@JsonKey(name: 'plate_number') final  String? plateNumber;
@override final  String? make;
@override final  String? colour;
@override@JsonKey() final  bool verified;

/// Create a copy of DriverVehicle
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DriverVehicleCopyWith<_DriverVehicle> get copyWith => __$DriverVehicleCopyWithImpl<_DriverVehicle>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DriverVehicleToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DriverVehicle&&(identical(other.type, type) || other.type == type)&&(identical(other.plateNumber, plateNumber) || other.plateNumber == plateNumber)&&(identical(other.make, make) || other.make == make)&&(identical(other.colour, colour) || other.colour == colour)&&(identical(other.verified, verified) || other.verified == verified));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,type,plateNumber,make,colour,verified);

@override
String toString() {
  return 'DriverVehicle(type: $type, plateNumber: $plateNumber, make: $make, colour: $colour, verified: $verified)';
}


}

/// @nodoc
abstract mixin class _$DriverVehicleCopyWith<$Res> implements $DriverVehicleCopyWith<$Res> {
  factory _$DriverVehicleCopyWith(_DriverVehicle value, $Res Function(_DriverVehicle) _then) = __$DriverVehicleCopyWithImpl;
@override @useResult
$Res call({
 String type,@JsonKey(name: 'plate_number') String? plateNumber, String? make, String? colour, bool verified
});




}
/// @nodoc
class __$DriverVehicleCopyWithImpl<$Res>
    implements _$DriverVehicleCopyWith<$Res> {
  __$DriverVehicleCopyWithImpl(this._self, this._then);

  final _DriverVehicle _self;
  final $Res Function(_DriverVehicle) _then;

/// Create a copy of DriverVehicle
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? type = null,Object? plateNumber = freezed,Object? make = freezed,Object? colour = freezed,Object? verified = null,}) {
  return _then(_DriverVehicle(
type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as String,plateNumber: freezed == plateNumber ? _self.plateNumber : plateNumber // ignore: cast_nullable_to_non_nullable
as String?,make: freezed == make ? _self.make : make // ignore: cast_nullable_to_non_nullable
as String?,colour: freezed == colour ? _self.colour : colour // ignore: cast_nullable_to_non_nullable
as String?,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

// dart format on
