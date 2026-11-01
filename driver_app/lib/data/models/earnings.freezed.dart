// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'earnings.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$EarningsReport {

 EarningsSummary get summary; EarningsBalance get balance; List<Payout> get payouts;@JsonKey(name: 'commission_pct') double get commissionPct;
/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EarningsReportCopyWith<EarningsReport> get copyWith => _$EarningsReportCopyWithImpl<EarningsReport>(this as EarningsReport, _$identity);

  /// Serializes this EarningsReport to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EarningsReport&&(identical(other.summary, summary) || other.summary == summary)&&(identical(other.balance, balance) || other.balance == balance)&&const DeepCollectionEquality().equals(other.payouts, payouts)&&(identical(other.commissionPct, commissionPct) || other.commissionPct == commissionPct));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,summary,balance,const DeepCollectionEquality().hash(payouts),commissionPct);

@override
String toString() {
  return 'EarningsReport(summary: $summary, balance: $balance, payouts: $payouts, commissionPct: $commissionPct)';
}


}

/// @nodoc
abstract mixin class $EarningsReportCopyWith<$Res>  {
  factory $EarningsReportCopyWith(EarningsReport value, $Res Function(EarningsReport) _then) = _$EarningsReportCopyWithImpl;
@useResult
$Res call({
 EarningsSummary summary, EarningsBalance balance, List<Payout> payouts,@JsonKey(name: 'commission_pct') double commissionPct
});


$EarningsSummaryCopyWith<$Res> get summary;$EarningsBalanceCopyWith<$Res> get balance;

}
/// @nodoc
class _$EarningsReportCopyWithImpl<$Res>
    implements $EarningsReportCopyWith<$Res> {
  _$EarningsReportCopyWithImpl(this._self, this._then);

  final EarningsReport _self;
  final $Res Function(EarningsReport) _then;

/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? summary = null,Object? balance = null,Object? payouts = null,Object? commissionPct = null,}) {
  return _then(_self.copyWith(
summary: null == summary ? _self.summary : summary // ignore: cast_nullable_to_non_nullable
as EarningsSummary,balance: null == balance ? _self.balance : balance // ignore: cast_nullable_to_non_nullable
as EarningsBalance,payouts: null == payouts ? _self.payouts : payouts // ignore: cast_nullable_to_non_nullable
as List<Payout>,commissionPct: null == commissionPct ? _self.commissionPct : commissionPct // ignore: cast_nullable_to_non_nullable
as double,
  ));
}
/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$EarningsSummaryCopyWith<$Res> get summary {
  
  return $EarningsSummaryCopyWith<$Res>(_self.summary, (value) {
    return _then(_self.copyWith(summary: value));
  });
}/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$EarningsBalanceCopyWith<$Res> get balance {
  
  return $EarningsBalanceCopyWith<$Res>(_self.balance, (value) {
    return _then(_self.copyWith(balance: value));
  });
}
}


/// Adds pattern-matching-related methods to [EarningsReport].
extension EarningsReportPatterns on EarningsReport {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _EarningsReport value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _EarningsReport() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _EarningsReport value)  $default,){
final _that = this;
switch (_that) {
case _EarningsReport():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _EarningsReport value)?  $default,){
final _that = this;
switch (_that) {
case _EarningsReport() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( EarningsSummary summary,  EarningsBalance balance,  List<Payout> payouts, @JsonKey(name: 'commission_pct')  double commissionPct)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _EarningsReport() when $default != null:
return $default(_that.summary,_that.balance,_that.payouts,_that.commissionPct);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( EarningsSummary summary,  EarningsBalance balance,  List<Payout> payouts, @JsonKey(name: 'commission_pct')  double commissionPct)  $default,) {final _that = this;
switch (_that) {
case _EarningsReport():
return $default(_that.summary,_that.balance,_that.payouts,_that.commissionPct);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( EarningsSummary summary,  EarningsBalance balance,  List<Payout> payouts, @JsonKey(name: 'commission_pct')  double commissionPct)?  $default,) {final _that = this;
switch (_that) {
case _EarningsReport() when $default != null:
return $default(_that.summary,_that.balance,_that.payouts,_that.commissionPct);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _EarningsReport implements EarningsReport {
  const _EarningsReport({required this.summary, required this.balance, final  List<Payout> payouts = const <Payout>[], @JsonKey(name: 'commission_pct') this.commissionPct = 0}): _payouts = payouts;
  factory _EarningsReport.fromJson(Map<String, dynamic> json) => _$EarningsReportFromJson(json);

@override final  EarningsSummary summary;
@override final  EarningsBalance balance;
 final  List<Payout> _payouts;
@override@JsonKey() List<Payout> get payouts {
  if (_payouts is EqualUnmodifiableListView) return _payouts;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_payouts);
}

@override@JsonKey(name: 'commission_pct') final  double commissionPct;

/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$EarningsReportCopyWith<_EarningsReport> get copyWith => __$EarningsReportCopyWithImpl<_EarningsReport>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$EarningsReportToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _EarningsReport&&(identical(other.summary, summary) || other.summary == summary)&&(identical(other.balance, balance) || other.balance == balance)&&const DeepCollectionEquality().equals(other._payouts, _payouts)&&(identical(other.commissionPct, commissionPct) || other.commissionPct == commissionPct));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,summary,balance,const DeepCollectionEquality().hash(_payouts),commissionPct);

@override
String toString() {
  return 'EarningsReport(summary: $summary, balance: $balance, payouts: $payouts, commissionPct: $commissionPct)';
}


}

/// @nodoc
abstract mixin class _$EarningsReportCopyWith<$Res> implements $EarningsReportCopyWith<$Res> {
  factory _$EarningsReportCopyWith(_EarningsReport value, $Res Function(_EarningsReport) _then) = __$EarningsReportCopyWithImpl;
@override @useResult
$Res call({
 EarningsSummary summary, EarningsBalance balance, List<Payout> payouts,@JsonKey(name: 'commission_pct') double commissionPct
});


@override $EarningsSummaryCopyWith<$Res> get summary;@override $EarningsBalanceCopyWith<$Res> get balance;

}
/// @nodoc
class __$EarningsReportCopyWithImpl<$Res>
    implements _$EarningsReportCopyWith<$Res> {
  __$EarningsReportCopyWithImpl(this._self, this._then);

  final _EarningsReport _self;
  final $Res Function(_EarningsReport) _then;

/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? summary = null,Object? balance = null,Object? payouts = null,Object? commissionPct = null,}) {
  return _then(_EarningsReport(
summary: null == summary ? _self.summary : summary // ignore: cast_nullable_to_non_nullable
as EarningsSummary,balance: null == balance ? _self.balance : balance // ignore: cast_nullable_to_non_nullable
as EarningsBalance,payouts: null == payouts ? _self._payouts : payouts // ignore: cast_nullable_to_non_nullable
as List<Payout>,commissionPct: null == commissionPct ? _self.commissionPct : commissionPct // ignore: cast_nullable_to_non_nullable
as double,
  ));
}

/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$EarningsSummaryCopyWith<$Res> get summary {
  
  return $EarningsSummaryCopyWith<$Res>(_self.summary, (value) {
    return _then(_self.copyWith(summary: value));
  });
}/// Create a copy of EarningsReport
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$EarningsBalanceCopyWith<$Res> get balance {
  
  return $EarningsBalanceCopyWith<$Res>(_self.balance, (value) {
    return _then(_self.copyWith(balance: value));
  });
}
}


/// @nodoc
mixin _$EarningsSummary {

 DateTime get from; DateTime get to;@JsonKey(name: 'jobs_count') int get jobsCount;@JsonKey(name: 'delivered_count') int get deliveredCount;@JsonKey(name: 'gross_fees') int get grossFees; int get commission; int get earnings;@JsonKey(name: 'cash_collected') int get cashCollected;@JsonKey(name: 'by_day') List<EarningsDay> get byDay; List<EarningsJob> get jobs;
/// Create a copy of EarningsSummary
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EarningsSummaryCopyWith<EarningsSummary> get copyWith => _$EarningsSummaryCopyWithImpl<EarningsSummary>(this as EarningsSummary, _$identity);

  /// Serializes this EarningsSummary to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EarningsSummary&&(identical(other.from, from) || other.from == from)&&(identical(other.to, to) || other.to == to)&&(identical(other.jobsCount, jobsCount) || other.jobsCount == jobsCount)&&(identical(other.deliveredCount, deliveredCount) || other.deliveredCount == deliveredCount)&&(identical(other.grossFees, grossFees) || other.grossFees == grossFees)&&(identical(other.commission, commission) || other.commission == commission)&&(identical(other.earnings, earnings) || other.earnings == earnings)&&(identical(other.cashCollected, cashCollected) || other.cashCollected == cashCollected)&&const DeepCollectionEquality().equals(other.byDay, byDay)&&const DeepCollectionEquality().equals(other.jobs, jobs));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,from,to,jobsCount,deliveredCount,grossFees,commission,earnings,cashCollected,const DeepCollectionEquality().hash(byDay),const DeepCollectionEquality().hash(jobs));

@override
String toString() {
  return 'EarningsSummary(from: $from, to: $to, jobsCount: $jobsCount, deliveredCount: $deliveredCount, grossFees: $grossFees, commission: $commission, earnings: $earnings, cashCollected: $cashCollected, byDay: $byDay, jobs: $jobs)';
}


}

/// @nodoc
abstract mixin class $EarningsSummaryCopyWith<$Res>  {
  factory $EarningsSummaryCopyWith(EarningsSummary value, $Res Function(EarningsSummary) _then) = _$EarningsSummaryCopyWithImpl;
@useResult
$Res call({
 DateTime from, DateTime to,@JsonKey(name: 'jobs_count') int jobsCount,@JsonKey(name: 'delivered_count') int deliveredCount,@JsonKey(name: 'gross_fees') int grossFees, int commission, int earnings,@JsonKey(name: 'cash_collected') int cashCollected,@JsonKey(name: 'by_day') List<EarningsDay> byDay, List<EarningsJob> jobs
});




}
/// @nodoc
class _$EarningsSummaryCopyWithImpl<$Res>
    implements $EarningsSummaryCopyWith<$Res> {
  _$EarningsSummaryCopyWithImpl(this._self, this._then);

  final EarningsSummary _self;
  final $Res Function(EarningsSummary) _then;

/// Create a copy of EarningsSummary
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? from = null,Object? to = null,Object? jobsCount = null,Object? deliveredCount = null,Object? grossFees = null,Object? commission = null,Object? earnings = null,Object? cashCollected = null,Object? byDay = null,Object? jobs = null,}) {
  return _then(_self.copyWith(
from: null == from ? _self.from : from // ignore: cast_nullable_to_non_nullable
as DateTime,to: null == to ? _self.to : to // ignore: cast_nullable_to_non_nullable
as DateTime,jobsCount: null == jobsCount ? _self.jobsCount : jobsCount // ignore: cast_nullable_to_non_nullable
as int,deliveredCount: null == deliveredCount ? _self.deliveredCount : deliveredCount // ignore: cast_nullable_to_non_nullable
as int,grossFees: null == grossFees ? _self.grossFees : grossFees // ignore: cast_nullable_to_non_nullable
as int,commission: null == commission ? _self.commission : commission // ignore: cast_nullable_to_non_nullable
as int,earnings: null == earnings ? _self.earnings : earnings // ignore: cast_nullable_to_non_nullable
as int,cashCollected: null == cashCollected ? _self.cashCollected : cashCollected // ignore: cast_nullable_to_non_nullable
as int,byDay: null == byDay ? _self.byDay : byDay // ignore: cast_nullable_to_non_nullable
as List<EarningsDay>,jobs: null == jobs ? _self.jobs : jobs // ignore: cast_nullable_to_non_nullable
as List<EarningsJob>,
  ));
}

}


/// Adds pattern-matching-related methods to [EarningsSummary].
extension EarningsSummaryPatterns on EarningsSummary {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _EarningsSummary value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _EarningsSummary() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _EarningsSummary value)  $default,){
final _that = this;
switch (_that) {
case _EarningsSummary():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _EarningsSummary value)?  $default,){
final _that = this;
switch (_that) {
case _EarningsSummary() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( DateTime from,  DateTime to, @JsonKey(name: 'jobs_count')  int jobsCount, @JsonKey(name: 'delivered_count')  int deliveredCount, @JsonKey(name: 'gross_fees')  int grossFees,  int commission,  int earnings, @JsonKey(name: 'cash_collected')  int cashCollected, @JsonKey(name: 'by_day')  List<EarningsDay> byDay,  List<EarningsJob> jobs)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _EarningsSummary() when $default != null:
return $default(_that.from,_that.to,_that.jobsCount,_that.deliveredCount,_that.grossFees,_that.commission,_that.earnings,_that.cashCollected,_that.byDay,_that.jobs);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( DateTime from,  DateTime to, @JsonKey(name: 'jobs_count')  int jobsCount, @JsonKey(name: 'delivered_count')  int deliveredCount, @JsonKey(name: 'gross_fees')  int grossFees,  int commission,  int earnings, @JsonKey(name: 'cash_collected')  int cashCollected, @JsonKey(name: 'by_day')  List<EarningsDay> byDay,  List<EarningsJob> jobs)  $default,) {final _that = this;
switch (_that) {
case _EarningsSummary():
return $default(_that.from,_that.to,_that.jobsCount,_that.deliveredCount,_that.grossFees,_that.commission,_that.earnings,_that.cashCollected,_that.byDay,_that.jobs);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( DateTime from,  DateTime to, @JsonKey(name: 'jobs_count')  int jobsCount, @JsonKey(name: 'delivered_count')  int deliveredCount, @JsonKey(name: 'gross_fees')  int grossFees,  int commission,  int earnings, @JsonKey(name: 'cash_collected')  int cashCollected, @JsonKey(name: 'by_day')  List<EarningsDay> byDay,  List<EarningsJob> jobs)?  $default,) {final _that = this;
switch (_that) {
case _EarningsSummary() when $default != null:
return $default(_that.from,_that.to,_that.jobsCount,_that.deliveredCount,_that.grossFees,_that.commission,_that.earnings,_that.cashCollected,_that.byDay,_that.jobs);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _EarningsSummary implements EarningsSummary {
  const _EarningsSummary({required this.from, required this.to, @JsonKey(name: 'jobs_count') this.jobsCount = 0, @JsonKey(name: 'delivered_count') this.deliveredCount = 0, @JsonKey(name: 'gross_fees') this.grossFees = 0, this.commission = 0, this.earnings = 0, @JsonKey(name: 'cash_collected') this.cashCollected = 0, @JsonKey(name: 'by_day') final  List<EarningsDay> byDay = const <EarningsDay>[], final  List<EarningsJob> jobs = const <EarningsJob>[]}): _byDay = byDay,_jobs = jobs;
  factory _EarningsSummary.fromJson(Map<String, dynamic> json) => _$EarningsSummaryFromJson(json);

@override final  DateTime from;
@override final  DateTime to;
@override@JsonKey(name: 'jobs_count') final  int jobsCount;
@override@JsonKey(name: 'delivered_count') final  int deliveredCount;
@override@JsonKey(name: 'gross_fees') final  int grossFees;
@override@JsonKey() final  int commission;
@override@JsonKey() final  int earnings;
@override@JsonKey(name: 'cash_collected') final  int cashCollected;
 final  List<EarningsDay> _byDay;
@override@JsonKey(name: 'by_day') List<EarningsDay> get byDay {
  if (_byDay is EqualUnmodifiableListView) return _byDay;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_byDay);
}

 final  List<EarningsJob> _jobs;
@override@JsonKey() List<EarningsJob> get jobs {
  if (_jobs is EqualUnmodifiableListView) return _jobs;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_jobs);
}


/// Create a copy of EarningsSummary
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$EarningsSummaryCopyWith<_EarningsSummary> get copyWith => __$EarningsSummaryCopyWithImpl<_EarningsSummary>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$EarningsSummaryToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _EarningsSummary&&(identical(other.from, from) || other.from == from)&&(identical(other.to, to) || other.to == to)&&(identical(other.jobsCount, jobsCount) || other.jobsCount == jobsCount)&&(identical(other.deliveredCount, deliveredCount) || other.deliveredCount == deliveredCount)&&(identical(other.grossFees, grossFees) || other.grossFees == grossFees)&&(identical(other.commission, commission) || other.commission == commission)&&(identical(other.earnings, earnings) || other.earnings == earnings)&&(identical(other.cashCollected, cashCollected) || other.cashCollected == cashCollected)&&const DeepCollectionEquality().equals(other._byDay, _byDay)&&const DeepCollectionEquality().equals(other._jobs, _jobs));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,from,to,jobsCount,deliveredCount,grossFees,commission,earnings,cashCollected,const DeepCollectionEquality().hash(_byDay),const DeepCollectionEquality().hash(_jobs));

@override
String toString() {
  return 'EarningsSummary(from: $from, to: $to, jobsCount: $jobsCount, deliveredCount: $deliveredCount, grossFees: $grossFees, commission: $commission, earnings: $earnings, cashCollected: $cashCollected, byDay: $byDay, jobs: $jobs)';
}


}

/// @nodoc
abstract mixin class _$EarningsSummaryCopyWith<$Res> implements $EarningsSummaryCopyWith<$Res> {
  factory _$EarningsSummaryCopyWith(_EarningsSummary value, $Res Function(_EarningsSummary) _then) = __$EarningsSummaryCopyWithImpl;
@override @useResult
$Res call({
 DateTime from, DateTime to,@JsonKey(name: 'jobs_count') int jobsCount,@JsonKey(name: 'delivered_count') int deliveredCount,@JsonKey(name: 'gross_fees') int grossFees, int commission, int earnings,@JsonKey(name: 'cash_collected') int cashCollected,@JsonKey(name: 'by_day') List<EarningsDay> byDay, List<EarningsJob> jobs
});




}
/// @nodoc
class __$EarningsSummaryCopyWithImpl<$Res>
    implements _$EarningsSummaryCopyWith<$Res> {
  __$EarningsSummaryCopyWithImpl(this._self, this._then);

  final _EarningsSummary _self;
  final $Res Function(_EarningsSummary) _then;

/// Create a copy of EarningsSummary
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? from = null,Object? to = null,Object? jobsCount = null,Object? deliveredCount = null,Object? grossFees = null,Object? commission = null,Object? earnings = null,Object? cashCollected = null,Object? byDay = null,Object? jobs = null,}) {
  return _then(_EarningsSummary(
from: null == from ? _self.from : from // ignore: cast_nullable_to_non_nullable
as DateTime,to: null == to ? _self.to : to // ignore: cast_nullable_to_non_nullable
as DateTime,jobsCount: null == jobsCount ? _self.jobsCount : jobsCount // ignore: cast_nullable_to_non_nullable
as int,deliveredCount: null == deliveredCount ? _self.deliveredCount : deliveredCount // ignore: cast_nullable_to_non_nullable
as int,grossFees: null == grossFees ? _self.grossFees : grossFees // ignore: cast_nullable_to_non_nullable
as int,commission: null == commission ? _self.commission : commission // ignore: cast_nullable_to_non_nullable
as int,earnings: null == earnings ? _self.earnings : earnings // ignore: cast_nullable_to_non_nullable
as int,cashCollected: null == cashCollected ? _self.cashCollected : cashCollected // ignore: cast_nullable_to_non_nullable
as int,byDay: null == byDay ? _self._byDay : byDay // ignore: cast_nullable_to_non_nullable
as List<EarningsDay>,jobs: null == jobs ? _self._jobs : jobs // ignore: cast_nullable_to_non_nullable
as List<EarningsJob>,
  ));
}


}


/// @nodoc
mixin _$EarningsDay {

 String get date; int get jobs; int get earnings;
/// Create a copy of EarningsDay
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EarningsDayCopyWith<EarningsDay> get copyWith => _$EarningsDayCopyWithImpl<EarningsDay>(this as EarningsDay, _$identity);

  /// Serializes this EarningsDay to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EarningsDay&&(identical(other.date, date) || other.date == date)&&(identical(other.jobs, jobs) || other.jobs == jobs)&&(identical(other.earnings, earnings) || other.earnings == earnings));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,date,jobs,earnings);

@override
String toString() {
  return 'EarningsDay(date: $date, jobs: $jobs, earnings: $earnings)';
}


}

/// @nodoc
abstract mixin class $EarningsDayCopyWith<$Res>  {
  factory $EarningsDayCopyWith(EarningsDay value, $Res Function(EarningsDay) _then) = _$EarningsDayCopyWithImpl;
@useResult
$Res call({
 String date, int jobs, int earnings
});




}
/// @nodoc
class _$EarningsDayCopyWithImpl<$Res>
    implements $EarningsDayCopyWith<$Res> {
  _$EarningsDayCopyWithImpl(this._self, this._then);

  final EarningsDay _self;
  final $Res Function(EarningsDay) _then;

/// Create a copy of EarningsDay
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? date = null,Object? jobs = null,Object? earnings = null,}) {
  return _then(_self.copyWith(
date: null == date ? _self.date : date // ignore: cast_nullable_to_non_nullable
as String,jobs: null == jobs ? _self.jobs : jobs // ignore: cast_nullable_to_non_nullable
as int,earnings: null == earnings ? _self.earnings : earnings // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [EarningsDay].
extension EarningsDayPatterns on EarningsDay {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _EarningsDay value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _EarningsDay() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _EarningsDay value)  $default,){
final _that = this;
switch (_that) {
case _EarningsDay():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _EarningsDay value)?  $default,){
final _that = this;
switch (_that) {
case _EarningsDay() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String date,  int jobs,  int earnings)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _EarningsDay() when $default != null:
return $default(_that.date,_that.jobs,_that.earnings);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String date,  int jobs,  int earnings)  $default,) {final _that = this;
switch (_that) {
case _EarningsDay():
return $default(_that.date,_that.jobs,_that.earnings);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String date,  int jobs,  int earnings)?  $default,) {final _that = this;
switch (_that) {
case _EarningsDay() when $default != null:
return $default(_that.date,_that.jobs,_that.earnings);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _EarningsDay implements EarningsDay {
  const _EarningsDay({required this.date, this.jobs = 0, this.earnings = 0});
  factory _EarningsDay.fromJson(Map<String, dynamic> json) => _$EarningsDayFromJson(json);

@override final  String date;
@override@JsonKey() final  int jobs;
@override@JsonKey() final  int earnings;

/// Create a copy of EarningsDay
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$EarningsDayCopyWith<_EarningsDay> get copyWith => __$EarningsDayCopyWithImpl<_EarningsDay>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$EarningsDayToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _EarningsDay&&(identical(other.date, date) || other.date == date)&&(identical(other.jobs, jobs) || other.jobs == jobs)&&(identical(other.earnings, earnings) || other.earnings == earnings));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,date,jobs,earnings);

@override
String toString() {
  return 'EarningsDay(date: $date, jobs: $jobs, earnings: $earnings)';
}


}

/// @nodoc
abstract mixin class _$EarningsDayCopyWith<$Res> implements $EarningsDayCopyWith<$Res> {
  factory _$EarningsDayCopyWith(_EarningsDay value, $Res Function(_EarningsDay) _then) = __$EarningsDayCopyWithImpl;
@override @useResult
$Res call({
 String date, int jobs, int earnings
});




}
/// @nodoc
class __$EarningsDayCopyWithImpl<$Res>
    implements _$EarningsDayCopyWith<$Res> {
  __$EarningsDayCopyWithImpl(this._self, this._then);

  final _EarningsDay _self;
  final $Res Function(_EarningsDay) _then;

/// Create a copy of EarningsDay
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? date = null,Object? jobs = null,Object? earnings = null,}) {
  return _then(_EarningsDay(
date: null == date ? _self.date : date // ignore: cast_nullable_to_non_nullable
as String,jobs: null == jobs ? _self.jobs : jobs // ignore: cast_nullable_to_non_nullable
as int,earnings: null == earnings ? _self.earnings : earnings // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}


/// @nodoc
mixin _$EarningsJob {

 int get id;@JsonKey(name: 'order_code') String? get orderCode; String get status; int get fee; int get commission;@JsonKey(name: 'earned_amount') int get earnedAmount;@JsonKey(name: 'payment_status') String? get paymentStatus;@JsonKey(name: 'distance_km') double? get distanceKm;@JsonKey(name: 'settled_at') DateTime? get settledAt;@JsonKey(name: 'paid_out') bool get paidOut;
/// Create a copy of EarningsJob
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EarningsJobCopyWith<EarningsJob> get copyWith => _$EarningsJobCopyWithImpl<EarningsJob>(this as EarningsJob, _$identity);

  /// Serializes this EarningsJob to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EarningsJob&&(identical(other.id, id) || other.id == id)&&(identical(other.orderCode, orderCode) || other.orderCode == orderCode)&&(identical(other.status, status) || other.status == status)&&(identical(other.fee, fee) || other.fee == fee)&&(identical(other.commission, commission) || other.commission == commission)&&(identical(other.earnedAmount, earnedAmount) || other.earnedAmount == earnedAmount)&&(identical(other.paymentStatus, paymentStatus) || other.paymentStatus == paymentStatus)&&(identical(other.distanceKm, distanceKm) || other.distanceKm == distanceKm)&&(identical(other.settledAt, settledAt) || other.settledAt == settledAt)&&(identical(other.paidOut, paidOut) || other.paidOut == paidOut));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,orderCode,status,fee,commission,earnedAmount,paymentStatus,distanceKm,settledAt,paidOut);

@override
String toString() {
  return 'EarningsJob(id: $id, orderCode: $orderCode, status: $status, fee: $fee, commission: $commission, earnedAmount: $earnedAmount, paymentStatus: $paymentStatus, distanceKm: $distanceKm, settledAt: $settledAt, paidOut: $paidOut)';
}


}

/// @nodoc
abstract mixin class $EarningsJobCopyWith<$Res>  {
  factory $EarningsJobCopyWith(EarningsJob value, $Res Function(EarningsJob) _then) = _$EarningsJobCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'order_code') String? orderCode, String status, int fee, int commission,@JsonKey(name: 'earned_amount') int earnedAmount,@JsonKey(name: 'payment_status') String? paymentStatus,@JsonKey(name: 'distance_km') double? distanceKm,@JsonKey(name: 'settled_at') DateTime? settledAt,@JsonKey(name: 'paid_out') bool paidOut
});




}
/// @nodoc
class _$EarningsJobCopyWithImpl<$Res>
    implements $EarningsJobCopyWith<$Res> {
  _$EarningsJobCopyWithImpl(this._self, this._then);

  final EarningsJob _self;
  final $Res Function(EarningsJob) _then;

/// Create a copy of EarningsJob
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? orderCode = freezed,Object? status = null,Object? fee = null,Object? commission = null,Object? earnedAmount = null,Object? paymentStatus = freezed,Object? distanceKm = freezed,Object? settledAt = freezed,Object? paidOut = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,orderCode: freezed == orderCode ? _self.orderCode : orderCode // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,fee: null == fee ? _self.fee : fee // ignore: cast_nullable_to_non_nullable
as int,commission: null == commission ? _self.commission : commission // ignore: cast_nullable_to_non_nullable
as int,earnedAmount: null == earnedAmount ? _self.earnedAmount : earnedAmount // ignore: cast_nullable_to_non_nullable
as int,paymentStatus: freezed == paymentStatus ? _self.paymentStatus : paymentStatus // ignore: cast_nullable_to_non_nullable
as String?,distanceKm: freezed == distanceKm ? _self.distanceKm : distanceKm // ignore: cast_nullable_to_non_nullable
as double?,settledAt: freezed == settledAt ? _self.settledAt : settledAt // ignore: cast_nullable_to_non_nullable
as DateTime?,paidOut: null == paidOut ? _self.paidOut : paidOut // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [EarningsJob].
extension EarningsJobPatterns on EarningsJob {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _EarningsJob value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _EarningsJob() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _EarningsJob value)  $default,){
final _that = this;
switch (_that) {
case _EarningsJob():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _EarningsJob value)?  $default,){
final _that = this;
switch (_that) {
case _EarningsJob() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'order_code')  String? orderCode,  String status,  int fee,  int commission, @JsonKey(name: 'earned_amount')  int earnedAmount, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'distance_km')  double? distanceKm, @JsonKey(name: 'settled_at')  DateTime? settledAt, @JsonKey(name: 'paid_out')  bool paidOut)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _EarningsJob() when $default != null:
return $default(_that.id,_that.orderCode,_that.status,_that.fee,_that.commission,_that.earnedAmount,_that.paymentStatus,_that.distanceKm,_that.settledAt,_that.paidOut);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'order_code')  String? orderCode,  String status,  int fee,  int commission, @JsonKey(name: 'earned_amount')  int earnedAmount, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'distance_km')  double? distanceKm, @JsonKey(name: 'settled_at')  DateTime? settledAt, @JsonKey(name: 'paid_out')  bool paidOut)  $default,) {final _that = this;
switch (_that) {
case _EarningsJob():
return $default(_that.id,_that.orderCode,_that.status,_that.fee,_that.commission,_that.earnedAmount,_that.paymentStatus,_that.distanceKm,_that.settledAt,_that.paidOut);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'order_code')  String? orderCode,  String status,  int fee,  int commission, @JsonKey(name: 'earned_amount')  int earnedAmount, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'distance_km')  double? distanceKm, @JsonKey(name: 'settled_at')  DateTime? settledAt, @JsonKey(name: 'paid_out')  bool paidOut)?  $default,) {final _that = this;
switch (_that) {
case _EarningsJob() when $default != null:
return $default(_that.id,_that.orderCode,_that.status,_that.fee,_that.commission,_that.earnedAmount,_that.paymentStatus,_that.distanceKm,_that.settledAt,_that.paidOut);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _EarningsJob implements EarningsJob {
  const _EarningsJob({required this.id, @JsonKey(name: 'order_code') this.orderCode, required this.status, this.fee = 0, this.commission = 0, @JsonKey(name: 'earned_amount') this.earnedAmount = 0, @JsonKey(name: 'payment_status') this.paymentStatus, @JsonKey(name: 'distance_km') this.distanceKm, @JsonKey(name: 'settled_at') this.settledAt, @JsonKey(name: 'paid_out') this.paidOut = false});
  factory _EarningsJob.fromJson(Map<String, dynamic> json) => _$EarningsJobFromJson(json);

@override final  int id;
@override@JsonKey(name: 'order_code') final  String? orderCode;
@override final  String status;
@override@JsonKey() final  int fee;
@override@JsonKey() final  int commission;
@override@JsonKey(name: 'earned_amount') final  int earnedAmount;
@override@JsonKey(name: 'payment_status') final  String? paymentStatus;
@override@JsonKey(name: 'distance_km') final  double? distanceKm;
@override@JsonKey(name: 'settled_at') final  DateTime? settledAt;
@override@JsonKey(name: 'paid_out') final  bool paidOut;

/// Create a copy of EarningsJob
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$EarningsJobCopyWith<_EarningsJob> get copyWith => __$EarningsJobCopyWithImpl<_EarningsJob>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$EarningsJobToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _EarningsJob&&(identical(other.id, id) || other.id == id)&&(identical(other.orderCode, orderCode) || other.orderCode == orderCode)&&(identical(other.status, status) || other.status == status)&&(identical(other.fee, fee) || other.fee == fee)&&(identical(other.commission, commission) || other.commission == commission)&&(identical(other.earnedAmount, earnedAmount) || other.earnedAmount == earnedAmount)&&(identical(other.paymentStatus, paymentStatus) || other.paymentStatus == paymentStatus)&&(identical(other.distanceKm, distanceKm) || other.distanceKm == distanceKm)&&(identical(other.settledAt, settledAt) || other.settledAt == settledAt)&&(identical(other.paidOut, paidOut) || other.paidOut == paidOut));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,orderCode,status,fee,commission,earnedAmount,paymentStatus,distanceKm,settledAt,paidOut);

@override
String toString() {
  return 'EarningsJob(id: $id, orderCode: $orderCode, status: $status, fee: $fee, commission: $commission, earnedAmount: $earnedAmount, paymentStatus: $paymentStatus, distanceKm: $distanceKm, settledAt: $settledAt, paidOut: $paidOut)';
}


}

/// @nodoc
abstract mixin class _$EarningsJobCopyWith<$Res> implements $EarningsJobCopyWith<$Res> {
  factory _$EarningsJobCopyWith(_EarningsJob value, $Res Function(_EarningsJob) _then) = __$EarningsJobCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'order_code') String? orderCode, String status, int fee, int commission,@JsonKey(name: 'earned_amount') int earnedAmount,@JsonKey(name: 'payment_status') String? paymentStatus,@JsonKey(name: 'distance_km') double? distanceKm,@JsonKey(name: 'settled_at') DateTime? settledAt,@JsonKey(name: 'paid_out') bool paidOut
});




}
/// @nodoc
class __$EarningsJobCopyWithImpl<$Res>
    implements _$EarningsJobCopyWith<$Res> {
  __$EarningsJobCopyWithImpl(this._self, this._then);

  final _EarningsJob _self;
  final $Res Function(_EarningsJob) _then;

/// Create a copy of EarningsJob
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? orderCode = freezed,Object? status = null,Object? fee = null,Object? commission = null,Object? earnedAmount = null,Object? paymentStatus = freezed,Object? distanceKm = freezed,Object? settledAt = freezed,Object? paidOut = null,}) {
  return _then(_EarningsJob(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,orderCode: freezed == orderCode ? _self.orderCode : orderCode // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,fee: null == fee ? _self.fee : fee // ignore: cast_nullable_to_non_nullable
as int,commission: null == commission ? _self.commission : commission // ignore: cast_nullable_to_non_nullable
as int,earnedAmount: null == earnedAmount ? _self.earnedAmount : earnedAmount // ignore: cast_nullable_to_non_nullable
as int,paymentStatus: freezed == paymentStatus ? _self.paymentStatus : paymentStatus // ignore: cast_nullable_to_non_nullable
as String?,distanceKm: freezed == distanceKm ? _self.distanceKm : distanceKm // ignore: cast_nullable_to_non_nullable
as double?,settledAt: freezed == settledAt ? _self.settledAt : settledAt // ignore: cast_nullable_to_non_nullable
as DateTime?,paidOut: null == paidOut ? _self.paidOut : paidOut // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$EarningsBalance {

@JsonKey(name: 'unpaid_earnings') int get unpaidEarnings;@JsonKey(name: 'cash_held') int get cashHeld; int get balance;
/// Create a copy of EarningsBalance
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EarningsBalanceCopyWith<EarningsBalance> get copyWith => _$EarningsBalanceCopyWithImpl<EarningsBalance>(this as EarningsBalance, _$identity);

  /// Serializes this EarningsBalance to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EarningsBalance&&(identical(other.unpaidEarnings, unpaidEarnings) || other.unpaidEarnings == unpaidEarnings)&&(identical(other.cashHeld, cashHeld) || other.cashHeld == cashHeld)&&(identical(other.balance, balance) || other.balance == balance));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,unpaidEarnings,cashHeld,balance);

@override
String toString() {
  return 'EarningsBalance(unpaidEarnings: $unpaidEarnings, cashHeld: $cashHeld, balance: $balance)';
}


}

/// @nodoc
abstract mixin class $EarningsBalanceCopyWith<$Res>  {
  factory $EarningsBalanceCopyWith(EarningsBalance value, $Res Function(EarningsBalance) _then) = _$EarningsBalanceCopyWithImpl;
@useResult
$Res call({
@JsonKey(name: 'unpaid_earnings') int unpaidEarnings,@JsonKey(name: 'cash_held') int cashHeld, int balance
});




}
/// @nodoc
class _$EarningsBalanceCopyWithImpl<$Res>
    implements $EarningsBalanceCopyWith<$Res> {
  _$EarningsBalanceCopyWithImpl(this._self, this._then);

  final EarningsBalance _self;
  final $Res Function(EarningsBalance) _then;

/// Create a copy of EarningsBalance
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? unpaidEarnings = null,Object? cashHeld = null,Object? balance = null,}) {
  return _then(_self.copyWith(
unpaidEarnings: null == unpaidEarnings ? _self.unpaidEarnings : unpaidEarnings // ignore: cast_nullable_to_non_nullable
as int,cashHeld: null == cashHeld ? _self.cashHeld : cashHeld // ignore: cast_nullable_to_non_nullable
as int,balance: null == balance ? _self.balance : balance // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [EarningsBalance].
extension EarningsBalancePatterns on EarningsBalance {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _EarningsBalance value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _EarningsBalance() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _EarningsBalance value)  $default,){
final _that = this;
switch (_that) {
case _EarningsBalance():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _EarningsBalance value)?  $default,){
final _that = this;
switch (_that) {
case _EarningsBalance() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function(@JsonKey(name: 'unpaid_earnings')  int unpaidEarnings, @JsonKey(name: 'cash_held')  int cashHeld,  int balance)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _EarningsBalance() when $default != null:
return $default(_that.unpaidEarnings,_that.cashHeld,_that.balance);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function(@JsonKey(name: 'unpaid_earnings')  int unpaidEarnings, @JsonKey(name: 'cash_held')  int cashHeld,  int balance)  $default,) {final _that = this;
switch (_that) {
case _EarningsBalance():
return $default(_that.unpaidEarnings,_that.cashHeld,_that.balance);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function(@JsonKey(name: 'unpaid_earnings')  int unpaidEarnings, @JsonKey(name: 'cash_held')  int cashHeld,  int balance)?  $default,) {final _that = this;
switch (_that) {
case _EarningsBalance() when $default != null:
return $default(_that.unpaidEarnings,_that.cashHeld,_that.balance);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _EarningsBalance implements EarningsBalance {
  const _EarningsBalance({@JsonKey(name: 'unpaid_earnings') this.unpaidEarnings = 0, @JsonKey(name: 'cash_held') this.cashHeld = 0, this.balance = 0});
  factory _EarningsBalance.fromJson(Map<String, dynamic> json) => _$EarningsBalanceFromJson(json);

@override@JsonKey(name: 'unpaid_earnings') final  int unpaidEarnings;
@override@JsonKey(name: 'cash_held') final  int cashHeld;
@override@JsonKey() final  int balance;

/// Create a copy of EarningsBalance
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$EarningsBalanceCopyWith<_EarningsBalance> get copyWith => __$EarningsBalanceCopyWithImpl<_EarningsBalance>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$EarningsBalanceToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _EarningsBalance&&(identical(other.unpaidEarnings, unpaidEarnings) || other.unpaidEarnings == unpaidEarnings)&&(identical(other.cashHeld, cashHeld) || other.cashHeld == cashHeld)&&(identical(other.balance, balance) || other.balance == balance));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,unpaidEarnings,cashHeld,balance);

@override
String toString() {
  return 'EarningsBalance(unpaidEarnings: $unpaidEarnings, cashHeld: $cashHeld, balance: $balance)';
}


}

/// @nodoc
abstract mixin class _$EarningsBalanceCopyWith<$Res> implements $EarningsBalanceCopyWith<$Res> {
  factory _$EarningsBalanceCopyWith(_EarningsBalance value, $Res Function(_EarningsBalance) _then) = __$EarningsBalanceCopyWithImpl;
@override @useResult
$Res call({
@JsonKey(name: 'unpaid_earnings') int unpaidEarnings,@JsonKey(name: 'cash_held') int cashHeld, int balance
});




}
/// @nodoc
class __$EarningsBalanceCopyWithImpl<$Res>
    implements _$EarningsBalanceCopyWith<$Res> {
  __$EarningsBalanceCopyWithImpl(this._self, this._then);

  final _EarningsBalance _self;
  final $Res Function(_EarningsBalance) _then;

/// Create a copy of EarningsBalance
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? unpaidEarnings = null,Object? cashHeld = null,Object? balance = null,}) {
  return _then(_EarningsBalance(
unpaidEarnings: null == unpaidEarnings ? _self.unpaidEarnings : unpaidEarnings // ignore: cast_nullable_to_non_nullable
as int,cashHeld: null == cashHeld ? _self.cashHeld : cashHeld // ignore: cast_nullable_to_non_nullable
as int,balance: null == balance ? _self.balance : balance // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}


/// @nodoc
mixin _$Payout {

 int get id; int get amount; String get status; String? get method; String? get reference;@JsonKey(name: 'period_start') String? get periodStart;@JsonKey(name: 'period_end') String? get periodEnd;@JsonKey(name: 'paid_at') DateTime? get paidAt;
/// Create a copy of Payout
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$PayoutCopyWith<Payout> get copyWith => _$PayoutCopyWithImpl<Payout>(this as Payout, _$identity);

  /// Serializes this Payout to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Payout&&(identical(other.id, id) || other.id == id)&&(identical(other.amount, amount) || other.amount == amount)&&(identical(other.status, status) || other.status == status)&&(identical(other.method, method) || other.method == method)&&(identical(other.reference, reference) || other.reference == reference)&&(identical(other.periodStart, periodStart) || other.periodStart == periodStart)&&(identical(other.periodEnd, periodEnd) || other.periodEnd == periodEnd)&&(identical(other.paidAt, paidAt) || other.paidAt == paidAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,amount,status,method,reference,periodStart,periodEnd,paidAt);

@override
String toString() {
  return 'Payout(id: $id, amount: $amount, status: $status, method: $method, reference: $reference, periodStart: $periodStart, periodEnd: $periodEnd, paidAt: $paidAt)';
}


}

/// @nodoc
abstract mixin class $PayoutCopyWith<$Res>  {
  factory $PayoutCopyWith(Payout value, $Res Function(Payout) _then) = _$PayoutCopyWithImpl;
@useResult
$Res call({
 int id, int amount, String status, String? method, String? reference,@JsonKey(name: 'period_start') String? periodStart,@JsonKey(name: 'period_end') String? periodEnd,@JsonKey(name: 'paid_at') DateTime? paidAt
});




}
/// @nodoc
class _$PayoutCopyWithImpl<$Res>
    implements $PayoutCopyWith<$Res> {
  _$PayoutCopyWithImpl(this._self, this._then);

  final Payout _self;
  final $Res Function(Payout) _then;

/// Create a copy of Payout
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? amount = null,Object? status = null,Object? method = freezed,Object? reference = freezed,Object? periodStart = freezed,Object? periodEnd = freezed,Object? paidAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,amount: null == amount ? _self.amount : amount // ignore: cast_nullable_to_non_nullable
as int,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,method: freezed == method ? _self.method : method // ignore: cast_nullable_to_non_nullable
as String?,reference: freezed == reference ? _self.reference : reference // ignore: cast_nullable_to_non_nullable
as String?,periodStart: freezed == periodStart ? _self.periodStart : periodStart // ignore: cast_nullable_to_non_nullable
as String?,periodEnd: freezed == periodEnd ? _self.periodEnd : periodEnd // ignore: cast_nullable_to_non_nullable
as String?,paidAt: freezed == paidAt ? _self.paidAt : paidAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

}


/// Adds pattern-matching-related methods to [Payout].
extension PayoutPatterns on Payout {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Payout value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Payout() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Payout value)  $default,){
final _that = this;
switch (_that) {
case _Payout():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Payout value)?  $default,){
final _that = this;
switch (_that) {
case _Payout() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  int amount,  String status,  String? method,  String? reference, @JsonKey(name: 'period_start')  String? periodStart, @JsonKey(name: 'period_end')  String? periodEnd, @JsonKey(name: 'paid_at')  DateTime? paidAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Payout() when $default != null:
return $default(_that.id,_that.amount,_that.status,_that.method,_that.reference,_that.periodStart,_that.periodEnd,_that.paidAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  int amount,  String status,  String? method,  String? reference, @JsonKey(name: 'period_start')  String? periodStart, @JsonKey(name: 'period_end')  String? periodEnd, @JsonKey(name: 'paid_at')  DateTime? paidAt)  $default,) {final _that = this;
switch (_that) {
case _Payout():
return $default(_that.id,_that.amount,_that.status,_that.method,_that.reference,_that.periodStart,_that.periodEnd,_that.paidAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  int amount,  String status,  String? method,  String? reference, @JsonKey(name: 'period_start')  String? periodStart, @JsonKey(name: 'period_end')  String? periodEnd, @JsonKey(name: 'paid_at')  DateTime? paidAt)?  $default,) {final _that = this;
switch (_that) {
case _Payout() when $default != null:
return $default(_that.id,_that.amount,_that.status,_that.method,_that.reference,_that.periodStart,_that.periodEnd,_that.paidAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Payout implements Payout {
  const _Payout({required this.id, this.amount = 0, required this.status, this.method, this.reference, @JsonKey(name: 'period_start') this.periodStart, @JsonKey(name: 'period_end') this.periodEnd, @JsonKey(name: 'paid_at') this.paidAt});
  factory _Payout.fromJson(Map<String, dynamic> json) => _$PayoutFromJson(json);

@override final  int id;
@override@JsonKey() final  int amount;
@override final  String status;
@override final  String? method;
@override final  String? reference;
@override@JsonKey(name: 'period_start') final  String? periodStart;
@override@JsonKey(name: 'period_end') final  String? periodEnd;
@override@JsonKey(name: 'paid_at') final  DateTime? paidAt;

/// Create a copy of Payout
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$PayoutCopyWith<_Payout> get copyWith => __$PayoutCopyWithImpl<_Payout>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$PayoutToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Payout&&(identical(other.id, id) || other.id == id)&&(identical(other.amount, amount) || other.amount == amount)&&(identical(other.status, status) || other.status == status)&&(identical(other.method, method) || other.method == method)&&(identical(other.reference, reference) || other.reference == reference)&&(identical(other.periodStart, periodStart) || other.periodStart == periodStart)&&(identical(other.periodEnd, periodEnd) || other.periodEnd == periodEnd)&&(identical(other.paidAt, paidAt) || other.paidAt == paidAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,amount,status,method,reference,periodStart,periodEnd,paidAt);

@override
String toString() {
  return 'Payout(id: $id, amount: $amount, status: $status, method: $method, reference: $reference, periodStart: $periodStart, periodEnd: $periodEnd, paidAt: $paidAt)';
}


}

/// @nodoc
abstract mixin class _$PayoutCopyWith<$Res> implements $PayoutCopyWith<$Res> {
  factory _$PayoutCopyWith(_Payout value, $Res Function(_Payout) _then) = __$PayoutCopyWithImpl;
@override @useResult
$Res call({
 int id, int amount, String status, String? method, String? reference,@JsonKey(name: 'period_start') String? periodStart,@JsonKey(name: 'period_end') String? periodEnd,@JsonKey(name: 'paid_at') DateTime? paidAt
});




}
/// @nodoc
class __$PayoutCopyWithImpl<$Res>
    implements _$PayoutCopyWith<$Res> {
  __$PayoutCopyWithImpl(this._self, this._then);

  final _Payout _self;
  final $Res Function(_Payout) _then;

/// Create a copy of Payout
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? amount = null,Object? status = null,Object? method = freezed,Object? reference = freezed,Object? periodStart = freezed,Object? periodEnd = freezed,Object? paidAt = freezed,}) {
  return _then(_Payout(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,amount: null == amount ? _self.amount : amount // ignore: cast_nullable_to_non_nullable
as int,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,method: freezed == method ? _self.method : method // ignore: cast_nullable_to_non_nullable
as String?,reference: freezed == reference ? _self.reference : reference // ignore: cast_nullable_to_non_nullable
as String?,periodStart: freezed == periodStart ? _self.periodStart : periodStart // ignore: cast_nullable_to_non_nullable
as String?,periodEnd: freezed == periodEnd ? _self.periodEnd : periodEnd // ignore: cast_nullable_to_non_nullable
as String?,paidAt: freezed == paidAt ? _self.paidAt : paidAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}


}

// dart format on
