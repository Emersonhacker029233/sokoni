<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Support\HandlesEmailChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user();

        return view('web.account.settings', [
            'user' => $user,
            'seller' => $user->sellerProfile,
            'title' => __('site.account_settings'),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update(['name' => $request->string('name')->toString(), 'locale' => $request->string('locale', $user->locale)->toString()]);
        HandlesEmailChange::apply($user, $request->filled('email') ? $request->string('email')->toString() : null);

        return back()->with('status', __('site.account_settings_saved'));
    }

    /** C5: a signed-in user with an unverified email can ask for the link again. */
    public function resendVerification(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->email === null || $user->hasVerifiedEmail()) {
            return back();
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', __('site.email_verification_resent'));
    }
}
