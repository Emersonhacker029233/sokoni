import 'dart:io' show Platform;

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../storage/secure_storage.dart';
import 'api_exception.dart';
import 'auth_interceptor.dart';

/// Base URL for the Laravel API. In release builds this defaults to the
/// live production API; in debug/profile builds it defaults to a loopback
/// address reachable from each dev target (Android emulators can't reach
/// the host machine's `127.0.0.1` directly — they need the special
/// `10.0.2.2` alias). `--dart-define=API_BASE_URL=...` overrides either
/// default, for a staging build or pointing a debug build at a real server.
String get sokoniApiBaseUrl {
  const override = String.fromEnvironment('API_BASE_URL');
  if (override.isNotEmpty) return override;

  if (kReleaseMode) return 'https://api.sokoni.co.tz/api';

  if (kIsWeb) return 'http://127.0.0.1:8000/api';
  if (Platform.isAndroid) return 'http://10.0.2.2:8000/api';
  return 'http://127.0.0.1:8000/api';
}

/// Builds the shared [Dio] instance: base URL, timeouts, the auth
/// interceptor, and a logging interceptor in debug builds only.
Dio buildDioClient({
  required SokoniSecureStorage storage,
  required Future<void> Function() onUnauthenticated,
}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: sokoniApiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 20),
      headers: {'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(AuthInterceptor(storage: storage, onUnauthenticated: onUnauthenticated));

  if (kDebugMode) {
    dio.interceptors.add(
      LogInterceptor(requestBody: true, responseBody: false, error: true),
    );
  }

  return dio;
}

/// Maps a [DioException] (or anything else thrown during a request) to a
/// typed [ApiException] repositories and UI code can branch on.
ApiException mapDioError(Object error) {
  if (error is! DioException) return const ServerException();

  switch (error.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.sendTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.transformTimeout:
    case DioExceptionType.connectionError:
      return const NetworkException();
    case DioExceptionType.badCertificate:
      return const NetworkException('Could not securely connect to the server.');
    case DioExceptionType.cancel:
      return const ServerException('Request cancelled.');
    case DioExceptionType.badResponse:
      return _mapResponse(error.response);
    case DioExceptionType.unknown:
      return const NetworkException();
  }
}

ApiException _mapResponse(Response? response) {
  final status = response?.statusCode;
  final data = response?.data;
  final message = (data is Map && data['message'] is String)
      ? data['message'] as String
      : null;

  switch (status) {
    case 401:
      return UnauthenticatedException(message ?? 'Please sign in again.');
    case 403:
      return ForbiddenException(message ?? 'Not allowed.');
    case 404:
      return NotFoundException(message ?? 'Not found.');
    case 422:
      final rawErrors = (data is Map ? data['errors'] : null) as Map?;
      final errors = <String, List<String>>{
        for (final entry in (rawErrors ?? {}).entries)
          entry.key as String: List<String>.from(entry.value as List),
      };
      return ValidationException(message ?? 'Please check the form and try again.', errors);
    case 429:
      return const RateLimitedException();
    default:
      return ServerException(message ?? 'Something went wrong. Please try again.');
  }
}
