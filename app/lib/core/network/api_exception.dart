/// Typed exceptions surfaced by [ApiClient] so UI code can branch on
/// failure kind (e.g. show cached data on [NetworkException], show field
/// errors on [ValidationException]) instead of parsing Dio internals.
sealed class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// The request never reached the server at all — DNS failure, connection
/// refused, no route to host (`DioExceptionType.connectionError`, which
/// Dio's own docs describe as "caused by SocketExceptions"). This is the
/// only exception that means "the device is actually offline" — callers
/// should fall back to cached data where available.
final class NetworkException extends ApiException {
  const NetworkException([super.message = 'No internet connection.']);
}

/// The connection was too slow to complete the request in time —
/// deliberately distinct from [NetworkException]. A device on patchy 3G
/// (this app's explicit CLAUDE.md target) times out routinely without
/// being offline; collapsing this into "no internet" is actively
/// misleading and was a real reported bug (a request timing out looked
/// identical to the device having no connection at all). Treated the same
/// as [NetworkException] by cache-aware repositories, since a timeout is
/// just as good a reason to fall back to a cached result.
///
/// Named `RequestTimeoutException`, not `TimeoutException` — `dart:async`
/// already declares a `TimeoutException`, and at least one file in this
/// app imports both libraries; reusing the name would make every bare
/// reference to it in that file ambiguous.
final class RequestTimeoutException extends ApiException {
  const RequestTimeoutException([
    super.message = 'The connection is slow — the request timed out. Check your signal and try again.',
  ]);
}

/// TLS handshake/certificate failure (`DioExceptionType.badCertificate`) —
/// the server was reached, but the secure connection couldn't be
/// established. Distinct from both of the above: retrying immediately
/// rarely helps the way it does for a timeout.
final class TlsException extends ApiException {
  const TlsException([super.message = 'Could not securely connect to the server.']);
}

/// 401 — the token is missing/invalid/expired. Callers should clear the
/// stored session and route to an unauthenticated state.
final class UnauthenticatedException extends ApiException {
  const UnauthenticatedException([super.message = 'Please sign in again.']);
}

/// 403 — authenticated, but not allowed to do this.
final class ForbiddenException extends ApiException {
  const ForbiddenException([super.message = 'Not allowed.']);
}

/// 404.
final class NotFoundException extends ApiException {
  const NotFoundException([super.message = 'Not found.']);
}

/// 422 — Laravel-style validation error payload: `{"errors": {"field": ["msg"]}}`.
final class ValidationException extends ApiException {
  const ValidationException(super.message, this.errors);

  final Map<String, List<String>> errors;

  /// First error message for [field], if any.
  String? fieldError(String field) => errors[field]?.firstOrNull;
}

/// 429 — rate limited.
final class RateLimitedException extends ApiException {
  const RateLimitedException([super.message = 'Too many requests — try again shortly.']);
}

/// 5xx or an unrecognised response shape from an otherwise-reached server.
final class ServerException extends ApiException {
  const ServerException([super.message = 'Something went wrong. Please try again.']);
}

/// `DioExceptionType.unknown` (or anything not even a [DioException]) —
/// Dio's own catch-all for failures that don't fit any other category
/// (a plugin-level failure, an unwrapped platform exception, etc). There
/// is no good generic human message for this bucket, so the constructor
/// requires the real underlying error text rather than defaulting to one
/// — surfacing it is what makes an otherwise-unrecognisable failure
/// diagnosable from the device instead of just another "something's wrong".
final class UnknownException extends ApiException {
  const UnknownException(super.message);
}
