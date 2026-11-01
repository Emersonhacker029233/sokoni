import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../data/models/earnings.dart';
import '../../data/repositories/driver_repository.dart';

/// `period` is `day` or `week`; a null [date] means "now".
typedef EarningsQuery = ({String period, DateTime? date});

final earningsProvider = FutureProvider.autoDispose.family<Cached<EarningsReport>, EarningsQuery>((ref, query) {
  return ref.watch(driverRepositoryProvider).earnings(period: query.period, date: query.date);
});
