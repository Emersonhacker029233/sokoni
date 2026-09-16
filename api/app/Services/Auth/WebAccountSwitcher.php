<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Part 5 (client feedback): "Instagram-style account switching... The
 * same behaviour using multiple sessions, if it can be done cleanly."
 *
 * It can: the website has no cart and stores no per-user state in the
 * PHP session itself (favourites, orders, messages, unread counts are
 * all DB relations keyed by `Auth::id()`), so switching which user the
 * `web` guard is logged in as switches every one of those correctly with
 * no separate cache to worry about — unlike the app, which has to guard
 * against the cart and disk-persisted drift caches leaking between
 * accounts, the website's leakage risk here is close to zero by
 * construction.
 *
 * The one real limitation, accepted deliberately rather than papered
 * over: Laravel's `remember` cookie is a single value per guard, not one
 * per linked account, so "remember me" only ever follows whichever
 * account is CURRENTLY active. If the PHP session itself expires (not
 * just the browser closing) while a non-active linked account was never
 * re-verified, that account falls out of `linked_account_ids` along with
 * the rest of the session and has to sign in again — the same way a
 * single-account site's remember-me already works today, just per
 * account rather than per browser. Building a real multi-cookie
 * remember-me scheme to remove that limitation was judged not worth the
 * added complexity for what it would buy.
 */
class WebAccountSwitcher
{
    private const SESSION_KEY = 'linked_account_ids';

    /**
     * Logs a freshly verified user in (a normal sign-in, or an "Add
     * account" sign-in while already authenticated as someone else) and
     * remembers them for this browser going forward.
     */
    public function login(Request $request, User $user): void
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->remember($request, $user->id);
    }

    /**
     * Switches the active account to one already remembered on this
     * browser — no re-verification, since this browser already proved
     * control of that account earlier in the same session.
     */
    public function switchTo(Request $request, int $userId): bool
    {
        if (! in_array($userId, $this->linkedIds($request), true)) {
            return false;
        }

        $user = User::query()->find($userId);
        if (! $user || $user->isBanned()) {
            return false;
        }

        Auth::loginUsingId($user->id, remember: true);
        $request->session()->regenerate();

        return true;
    }

    /**
     * Part 5: "Signing out removes only the active account and returns
     * to the next one, or to guest if it was the last." Mirrors the
     * app's `SokoniSecureStorage::clearSession()` exactly: drop the
     * active id, activate the next remaining one if any, otherwise a
     * real full logout.
     */
    public function signOutActive(Request $request): void
    {
        $activeId = Auth::id();
        $remaining = array_values(array_filter(
            $this->linkedIds($request),
            fn (int $id): bool => $id !== $activeId,
        ));
        $request->session()->put(self::SESSION_KEY, $remaining);

        if ($remaining !== []) {
            Auth::loginUsingId($remaining[0], remember: true);
            $request->session()->regenerate();

            return;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** Every account this browser is currently signed into, active one included — for the switcher UI. */
    public function linkedAccounts(Request $request): Collection
    {
        return User::query()->whereIn('id', $this->linkedIds($request))->get();
    }

    private function remember(Request $request, int $userId): void
    {
        $ids = $this->linkedIds($request);
        if (! in_array($userId, $ids, true)) {
            $ids[] = $userId;
            $request->session()->put(self::SESSION_KEY, $ids);
        }
    }

    /** @return list<int> */
    private function linkedIds(Request $request): array
    {
        return $request->session()->get(self::SESSION_KEY, []);
    }
}
