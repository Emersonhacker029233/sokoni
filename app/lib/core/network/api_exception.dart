/// Typed exceptions surfaced by [ApiClient] so UI code can branch on
/// failure kind (e.g. show cached data on [NetworkException], show field
/// errors on [ValidationException]) instead of parsing Dio internals.
sealed class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// No connectivity, timeout, or the request otherwise never reached the
/// server — callers should fall back to cached data where available.
final class NetworkException extends ApiException {
  const NetworkException([super.message = 'No internet connection.']);
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

/// 5xx or an unrecognised failure shape.
final class ServerException extends ApiException {
  const ServerException([super.message = 'Something went wrong. Please try again.']);
}
