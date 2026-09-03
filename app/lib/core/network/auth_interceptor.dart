import 'package:dio/dio.dart';

import '../storage/secure_storage.dart';

/// Attaches the Sanctum bearer token to every request, and reacts to a 401
/// by clearing the stored session and notifying [onUnauthenticated] so the
/// router can drop back to the unauthenticated state. Sanctum's personal
/// access tokens don't expire on a schedule (no OAuth-style refresh
/// token, confirmed via `config/sanctum.php`'s `'expiration' => null`), so
/// "handling expiry" here means "the server rejected it" — revoked,
/// deleted, or the account was banned — not a proactive refresh.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({required this.storage, required this.onUnauthenticated});

  final SokoniSecureStorage storage;
  final Future<void> Function() onUnauthenticated;

  /// Marks whether a token was actually attached to this specific request —
  /// see [onError] for why the distinction matters.
  static const _hadTokenKey = 'sokoni.had_token';

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await storage.readToken();
    options.extra[_hadTokenKey] = token != null;
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    // Only a token the server actually saw and rejected means "this session
    // is really gone" — clearing storage here otherwise would mean a purely
    // local hiccup (a transient secure-storage read failure that made
    // onRequest above attach no header at all) gets misread as proof the
    // session is invalid, and actively deletes a token that was never
    // actually rejected by anything. See DECISIONS.md.
    final hadToken = err.requestOptions.extra[_hadTokenKey] == true;
    if (err.response?.statusCode == 401 && hadToken) {
      await storage.clearSession();
      await onUnauthenticated();
    }
    handler.next(err);
  }
}
