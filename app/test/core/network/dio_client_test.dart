import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/core/network/dio_client.dart';

/// Tester feedback B5 ("checkout fails with an error") traced to a
/// codebase-wide bug, not anything checkout-specific: Laravel's top-level
/// "message" on every 422 response is always the same generic "The given
/// data was invalid.", regardless of which field actually failed — the
/// real, specific, actionable reason only ever lives in the "errors" map.
/// `mapDioError` used to surface the generic wrapper; it should surface
/// the first real field error instead.
void main() {
  DioException validationError(Map<String, dynamic> data) {
    final requestOptions = RequestOptions(path: '/orders');
    return DioException(
      requestOptions: requestOptions,
      type: DioExceptionType.badResponse,
      response: Response(requestOptions: requestOptions, statusCode: 422, data: data),
    );
  }

  test('surfaces the specific field error, not Laravel\'s generic wrapper message', () {
    final exception = mapDioError(validationError({
      'message': 'The given data was invalid.',
      'errors': {
        'items': ['All items in one order must be from the same seller.'],
      },
    }));

    expect(exception, isA<ValidationException>());
    expect(exception.message, 'All items in one order must be from the same seller.');
  });

  test('a checkout-shaped address-required error is specific too', () {
    final exception = mapDioError(validationError({
      'message': 'The given data was invalid.',
      'errors': {
        'address': ['The address field is required when delivery method is delivery.'],
      },
    }));

    expect(exception.message, 'The address field is required when delivery method is delivery.');
  });

  test('falls back to the generic message when errors is empty', () {
    final exception = mapDioError(validationError({
      'message': 'The given data was invalid.',
      'errors': <String, dynamic>{},
    }));

    expect(exception.message, 'The given data was invalid.');
  });

  test('falls back to a default message when the response body has neither', () {
    final exception = mapDioError(validationError({}));

    expect(exception.message, 'Please check the form and try again.');
  });

  test('the full errors map is still available for field-level display', () {
    final exception = mapDioError(validationError({
      'errors': {
        'rating': ['The rating field is required.'],
        'order': ['Only completed orders can be reviewed.'],
      },
    }));

    expect(exception, isA<ValidationException>());
    final validation = exception as ValidationException;
    expect(validation.fieldError('rating'), 'The rating field is required.');
    expect(validation.fieldError('order'), 'Only completed orders can be reviewed.');
  });
}
