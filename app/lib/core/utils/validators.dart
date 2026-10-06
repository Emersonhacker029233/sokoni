import 'package:flutter/widgets.dart';

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
      // Part 2 (client feedback): "describe the local format the user
      // is actually entering" — the field itself now shows a fixed
      // +255 prefix (SokoniPhoneField), so the user only ever types the
      // local part; the error should describe that, not the E.164 shape
      // they never see or type.
      return 'Enter a valid 9-digit number, e.g. 712 345 678 or 0712 345 678';
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

  /// Username/password rework (CLAUDE.md Part D) — same shape as [handle]
  /// (`User::USERNAME_PATTERN` server-side), a separate validator since a
  /// username and a shop handle are different concepts that happen to
  /// share a format today.
  static String? username(String? value) {
    if (value == null || value.trim().isEmpty) return 'Choose a username';
    if (!RegExp(r'^[a-z0-9_]{3,20}$').hasMatch(value)) {
      return '3-20 characters: lowercase letters, numbers, underscore';
    }
    return null;
  }

  static String? password(String? value) {
    if (value == null || value.isEmpty) return 'Choose a password';
    if (value.length < 8) return 'At least 8 characters';
    return null;
  }

  static String? Function(String?) passwordConfirmation(TextEditingController password) {
    return (value) {
      if (value == null || value.isEmpty) return 'Confirm your password';
      if (value != password.text) return 'Passwords do not match';
      return null;
    };
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

  /// Email is always optional (CLAUDE.md C5) — only the format is checked,
  /// and only when something was actually typed.
  static String? optionalEmail(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value.trim())) {
      return 'Enter a valid email address';
    }
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
