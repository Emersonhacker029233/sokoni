<?php

namespace App\Support;

use App\Models\SellerProfile;
use App\Models\User;

/**
 * Who may see Bolt delivery and returns (dark launch, docs/delivery/BOLT.md).
 *
 * Delivery is available to a user only when ALL of these hold:
 *  - BOLT_ENABLED=true,
 *  - BOLT_TEST_PHONES is empty, or lists the user's phone,
 *  - the user is not the App Store review account (REVIEW_ACCOUNT_PHONE).
 * Returns additionally need DELIVERY_RETURNS_ENABLED=true.
 *
 * For anyone else the API behaves exactly as before this feature existed:
 * delivery routes 404, checkout ignores `fulfilment`, and no response
 * carries a delivery or return field.
 */
final class DeliveryAccess
{
    public static function deliveryEnabledFor(?User $user): bool
    {
        if (! config('delivery.enabled') || $user === null || self::isReviewAccount($user)) {
            return false;
        }

        $allowlist = self::testPhones();

        return $allowlist === [] || ($user->phone !== null && in_array(self::normalise($user->phone), $allowlist, true));
    }

    public static function returnsEnabledFor(?User $user): bool
    {
        return (bool) config('delivery.returns_enabled') && self::deliveryEnabledFor($user);
    }

    /**
     * A Bolt delivery order needs both sides inside the launch: while
     * BOLT_TEST_PHONES is set, a test buyer can only order delivery from a
     * test shop, so no real shop is ever sent a Bolt driver it doesn't
     * expect.
     */
    public static function orderEligible(?User $buyer, ?SellerProfile $shop): bool
    {
        return self::deliveryEnabledFor($buyer) && self::deliveryEnabledFor($shop?->user);
    }

    public static function returnsEligible(?User $buyer, ?SellerProfile $shop): bool
    {
        return self::returnsEnabledFor($buyer) && self::returnsEnabledFor($shop?->user);
    }

    /** The App Store review account never sees delivery or returns, whatever the allowlist says. */
    public static function isReviewAccount(User $user): bool
    {
        $reviewPhone = config('services.review_account.phone');

        return is_string($reviewPhone) && $reviewPhone !== '' && $user->phone !== null
            && self::normalise($user->phone) === self::normalise($reviewPhone);
    }

    /** @return list<string> E.164 numbers from BOLT_TEST_PHONES */
    public static function testPhones(): array
    {
        $raw = config('delivery.test_phones');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map(fn ($p) => self::normalise((string) $p), $list))));
    }

    /** "+255 754-123 456", "255754123456" and "0754123456" all become "+255754123456". */
    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '255')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+255'.substr($digits, 1);
        }

        return $digits;
    }
}
