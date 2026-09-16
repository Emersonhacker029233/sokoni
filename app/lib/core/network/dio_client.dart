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
///
/// Timeouts are deliberately generous — CLAUDE.md targets "mid-range
/// Android on patchy 3G/4G", and a real device on 3G was seen timing out
/// against the previous 15s/20s defaults on a plain product-list request,
/// which [mapDioError] then (wrongly, see its own docs) reported as "no
/// internet connection" rather than "that was slow". 30s is the floor for
/// establishing a connection at all on a congested 3G cell; receive gets
/// longer still since it covers the full response body transfer, not just
/// the handshake.
Dio buildDioClient({
  required SokoniSecureStorage storage,
  required Future<void> Function() onUnauthenticated,
}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: sokoniApiBaseUrl,
      connectTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 45),
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
///
/// Every case below maps to its own distinct message — a real reported bug
/// was every one of timeout/DNS-failure/TLS-failure/5xx/unrecognised-error
/// collapsing into the same "No internet connection." text, which made a
/// slow-3G timeout indistinguishable from the device genuinely being
/// offline (and was diagnosed as exactly that: the API was reachable and
/// answering in the browser at the same moment the app reported "offline",
/// because the app's own request had already timed out against the old
/// 15s connect timeout and gotten misclassified).
ApiException mapDioError(Object error) {
  if (error is! DioException) return UnknownException('Unexpected error: $error');

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
      return UnknownException(_describeUnknown(error));
  }
}

/// Dio's catch-all bucket has no reliable shape — `error.error` is
/// whatever the underlying platform/plugin call threw, if anything. Prefer
/// that (it's the actual cause), fall back to Dio's own message, and only
/// then to a generic line — never silently reuse another category's text.
String _describeUnknown(DioException error) {
  final inner = error.error;
  if (inner != null) return inner.toString();
  if (error.message != null && error.message!.isNotEmpty) return error.message!;
  return 'An unexpected error occurred.';
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
      // Laravel's top-level "message" on a validation error is always the
      // same generic "The given data was invalid." regardless of which
      // field actually failed — the real, specific, actionable reason
      // (e.g. "All items in one order must be from the same seller",
      // "Please provide a delivery address") only ever lives in `errors`.
      // Surfacing the generic wrapper instead of it is exactly what made
      // checkout (and every other form) "fail with an error" with nothing
      // useful to act on (tester feedback B5).
      final firstFieldError = errors.values.firstOrNull?.firstOrNull;
      return ValidationException(firstFieldError ?? message ?? 'Please check the form and try again.', errors);
    case 429:
      // Part 3 (client feedback): "say so clearly when the limit is
      // reached" — Laravel's throttle middleware always sends a real
      // Retry-After header on a 429, so this is the server's own actual
      // remaining lockout, not a client guess.
      final retryAfter = response?.headers.value('retry-after');
      return RateLimitedException(
        message ?? 'Too many requests — try again shortly.',
        retryAfter != null ? int.tryParse(retryAfter) : null,
      );
    default:
      return ServerException(message ?? 'Server error${status != null ? ' ($status)' : ''}. Please try again.');
  }
}
