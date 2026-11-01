import 'dart:io' show Platform;

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../storage/secure_storage.dart';
import 'api_exception.dart';

/// Base URL for the Laravel API. Release builds default to production,
/// debug builds to the local `php artisan serve` (10.0.2.2 is the host
/// machine from the Android emulator). `--dart-define=API_BASE_URL=...`
/// overrides both, e.g. a phone on the same Wi-Fi as the laptop.
String get apiBaseUrl {
  const override = String.fromEnvironment('API_BASE_URL');
  if (override.isNotEmpty) return override;
  if (kReleaseMode) return 'https://api.sokoni.co.tz/api';
  if (!kIsWeb && Platform.isAndroid) return 'http://10.0.2.2:8000/api';
  return 'http://127.0.0.1:8000/api';
}

/// Shared Dio: base URL, the generous timeouts the customer app settled on
/// for 3G, the bearer token, the app's language for localised server
/// messages, and a 401 handler that signs the driver out.
Dio buildDio({
  required DriverSecureStorage storage,
  required String Function() languageCode,
  required Future<void> Function() onUnauthenticated,
}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: apiBaseUrl,
      connectTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 60),
      receiveTimeout: const Duration(seconds: 45),
      headers: {'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await storage.readToken();
        options.extra['had_token'] = token != null;
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        options.headers['Accept-Language'] = languageCode();
        handler.next(options);
      },
      onError: (err, handler) async {
        // Only a token the server saw and rejected ends the session.
        if (err.response?.statusCode == 401 && err.requestOptions.extra['had_token'] == true) {
          await onUnauthenticated();
        }
        handler.next(err);
      },
    ),
  );

  if (kDebugMode) {
    dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: false, error: true));
  }

  return dio;
}

/// Maps anything thrown during a request to a typed [ApiException].
ApiException mapDioError(Object error) {
  if (error is ApiException) return error;
  if (error is! DioException) return UnknownException('$error');

  switch (error.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.sendTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.transformTimeout:
      return const RequestTimeoutException();
    case DioExceptionType.connectionError:
      return const NetworkException();
    case DioExceptionType.badCertificate:
      return const TlsException();
    case DioExceptionType.cancel:
      return const ServerException('Request cancelled.');
    case DioExceptionType.badResponse:
      return _mapResponse(error.response);
    case DioExceptionType.unknown:
      final inner = error.error;
      if (inner is ApiException) return inner;
      return UnknownException(inner?.toString() ?? error.message ?? 'An unexpected error occurred.');
  }
}

ApiException _mapResponse(Response<dynamic>? response) {
  final status = response?.statusCode;
  final data = response?.data;
  final body = data is Map ? Map<String, dynamic>.from(data) : const <String, dynamic>{};
  final message = body['message'] is String ? body['message'] as String : null;

  // Delivery actions answer with {code, message, ...extra}.
  if (body['code'] is String && status != null && status >= 400 && status < 500 && status != 401 && status != 429) {
    return DeliveryActionException(
      message ?? body['code'] as String,
      code: body['code'] as String,
      statusCode: status,
      data: body,
    );
  }

  switch (status) {
    case 401:
      return UnauthenticatedException(message ?? 'Please sign in again.');
    case 403:
      return ForbiddenException(message ?? 'Not allowed.');
    case 404:
      return NotFoundException(message ?? 'Not found.');
    case 422:
      final rawErrors = body['errors'] is Map ? body['errors'] as Map : const {};
      final errors = <String, List<String>>{
        for (final entry in rawErrors.entries) entry.key as String: List<String>.from(entry.value as List),
      };
      final first = errors.values.firstOrNull?.firstOrNull;
      return ValidationException(first ?? message ?? 'Please check the form and try again.', errors);
    case 429:
      final retryAfter = response?.headers.value('retry-after');
      return RateLimitedException(
        message ?? 'Too many requests. Try again shortly.',
        retryAfter != null ? int.tryParse(retryAfter) : null,
      );
    default:
      return ServerException(message ?? 'Server error${status != null ? ' ($status)' : ''}. Please try again.');
  }
}

/// True when [e] means "couldn't reach the server", the cases where the
/// app shows cached data or queues the action for later.
bool isConnectivityError(Object e) => e is NetworkException || e is RequestTimeoutException;
