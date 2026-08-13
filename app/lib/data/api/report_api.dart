import 'package:dio/dio.dart' hide Headers;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retrofit/retrofit.dart';

import '../../core/providers.dart';

part 'report_api.g.dart';

@RestApi()
abstract class ReportApi {
  factory ReportApi(Dio dio, {String baseUrl}) = _ReportApi;

  @POST('/reports')
  Future<dynamic> submit(@Body() Map<String, dynamic> body);
}

final reportApiProvider = Provider<ReportApi>((ref) => ReportApi(ref.watch(dioProvider)));
