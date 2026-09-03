<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Chats and Profile are two of the website's 5 primary nav destinations,
 * but both require an account — per instruction, a signed-out visitor gets
 * an explanation of what signing in unlocks, not an immediate redirect to
 * /login the way every other auth-gated route on this site behaves (see
 * bootstrap/app.php's redirectGuestsTo). These two routes sit outside the
 * auth:web middleware group specifically so a guest can reach them at all;
 * a signed-in visitor is sent straight through to the real page.
 */
class NavGateController extends Controller
{
    public function chats(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('web.account.messages');
        }

        return view('web.auth.signin-prompt', [
            'feature' => 'chats',
            'title' => __('site.nav_chats'),
        ]);
    }

    public function profile(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('web.account.dashboard');
        }

        return view('web.auth.signin-prompt', [
            'feature' => 'profile',
            'title' => __('site.nav_profile'),
        ]);
    }
}
