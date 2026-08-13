import 'package:dio/dio.dart';

import '../storage/secure_storage.dart';

/// Attaches the Sanctum bearer token to every request, and reacts to a 401
/// by clearing the stored session and notifying [onUnauthenticated] so the
/// router can drop back to the unauthenticated state. Sanctum's personal
/// access tokens don't expire on a schedule (no OAuth-style refresh
/// token), so "handling expiry" here means "the server rejected it" —
/// revoked, deleted, or the account was banned — not a proactive refresh.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({required this.storage, required this.onUnauthenticated});

  final SokoniSecureStorage storage;
  final Future<void> Function() onUnauthenticated;

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await storage.readToken();
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      await storage.clearSession();
      await onUnauthenticated();
    }
    handler.next(err);
  }
}
