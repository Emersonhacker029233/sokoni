// Null-tolerant readers for the generated (json_serializable) models.
//
// The API's resources normally send every field read here, but a field
// the server leaves null must not make the app fail to read a response
// for something that already happened on the server. On 2026-11-04 the
// order-placing call answered `"status": null`; the order was created,
// yet Sokoni 1.1 showed "type 'Null' is not a subtype of type 'String'".
//
// Text and flags fall back to a neutral value. Amounts are deliberately
// NOT read through here: a missing price, fee or total stays an error
// rather than becoming a TSh 0 the server never recorded.

/// Any value as text; null becomes ''.
String readString(Object? value) => value == null ? '' : '$value';

/// An order status; a missing one is 'pending', the orders table default
/// and the status of every order the moment it is placed.
String readOrderStatus(Object? value) => value is String && value.isNotEmpty ? value : 'pending';

/// true, 1, '1' and 'true' are true; anything else, null included, is false.
bool readBool(Object? value) => value == true || value == 1 || value == '1' || value == 'true';

/// An ISO-8601 timestamp, or null when missing or unreadable.
DateTime? readDateTimeOrNull(Object? value) => value is String ? DateTime.tryParse(value) : null;
