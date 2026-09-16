<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\WebAccountSwitcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Part 5 (client feedback): "an account switcher... switching is
 * instant — swap the active token, refresh providers, no
 * re-verification." The website equivalent of the app's
 * `AuthStateController.switchAccount()`: [$targetUser] must already be
 * one this browser verified via OTP/Google earlier in the same session
 * (see WebAccountSwitcher) — this never accepts an arbitrary user id.
 */
class AccountSwitchController extends Controller
{
    public function switch(Request $request, User $targetUser, WebAccountSwitcher $switcher): RedirectResponse
    {
        $switcher->switchTo($request, $targetUser->id);

        return redirect()->route('web.account.dashboard');
    }
}
