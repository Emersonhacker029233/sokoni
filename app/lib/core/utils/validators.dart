import 'formatters.dart';

/// Form field validators returning a user-facing error string, or null
/// when the value is valid — the shape [TextFormField.validator] expects.
abstract final class SokoniValidators {
  static String? required(String? value, {String message = 'This field is required'}) {
    if (value == null || value.trim().isEmpty) return message;
    return null;
  }

  static String? phone(String? value) {
    if (value == null || value.trim().isEmpty) return 'Phone number is required';
    if (SokoniFormat.phoneToE164(value) == null) {
      return 'Enter a valid Tanzanian phone number';
    }
    return null;
  }

  static String? otpCode(String? value) {
    if (value == null || value.trim().isEmpty) return 'Enter the code';
    if (!RegExp(r'^\d{6}$').hasMatch(value.trim())) return 'Enter the 6-digit code';
    return null;
  }

  static String? handle(String? value) {
    if (value == null || value.trim().isEmpty) return 'Choose a handle';
    if (!RegExp(r'^[a-z0-9_]{3,20}$').hasMatch(value)) {
      return '3-20 characters: lowercase letters, numbers, underscore';
    }
    return null;
  }

  static String? nidaNumber(String? value) {
    if (value == null || value.trim().isEmpty) return 'NIDA number is required';
    if (!RegExp(r'^\d{20}$').hasMatch(value.trim())) return 'NIDA number must be 20 digits';
    return null;
  }

  static String? price(String? value) {
    if (value == null || value.trim().isEmpty) return 'Enter a price';
    final n = num.tryParse(value.replaceAll(',', ''));
    if (n == null || n < 0) return 'Enter a valid price';
    return null;
  }

  static String? Function(String?) minLength(int min, {String? message}) {
    return (value) {
      if (value == null || value.trim().length < min) {
        return message ?? 'Must be at least $min characters';
      }
      return null;
    };
  }
}
