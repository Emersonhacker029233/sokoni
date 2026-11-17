/// One row of `GET /blocks` (Apple Guideline 1.2): someone the signed-in
/// user blocked. [sellerId] is set when that person owns a shop, so the
/// shop's listings, posts and reviews can be left out too, not only what
/// they wrote as a buyer. Never carries a phone, email or username.
class BlockedAccount {
  const BlockedAccount({
    required this.userId,
    required this.name,
    this.avatar,
    this.sellerId,
    this.shopName,
    this.handle,
    this.logo,
  });

  factory BlockedAccount.fromJson(Map<String, dynamic> json) => BlockedAccount(
    userId: json['user_id'] as int,
    name: json['name'] is String ? json['name'] as String : '',
    avatar: json['avatar'] as String?,
    sellerId: json['seller_id'] as int?,
    shopName: json['shop_name'] as String?,
    handle: json['handle'] as String?,
    logo: json['logo'] as String?,
  );

  final int userId;
  final String name;
  final String? avatar;
  final int? sellerId;
  final String? shopName;
  final String? handle;
  final String? logo;

  /// The shop's name for a seller, otherwise the person's own name.
  String get displayName => shopName ?? name;
}
