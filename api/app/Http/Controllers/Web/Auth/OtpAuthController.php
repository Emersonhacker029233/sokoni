<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\Otp\PhoneOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phone OTP register/login for the website — reuses exactly the same
 * `PhoneOtpService` (and therefore the same `SmsGateway` binding) the
 * mobile app's API endpoints use, not a parallel implementation. The one
 * real difference is session vs. token: `Auth::login()` on the `web`
 * guard here, a Sanctum token there — see CLAUDE.md: "Session-based auth
 * for web..., completely separate from the API's Sanctum tokens."
 */
class OtpAuthController extends Controller
{
    public function __construct(private readonly PhoneOtpService $otp) {}

    public function show(Request $request): View
    {
        return view('web.auth.login', [
            'step' => $request->session()->get('otp_phone') ? 'code' : 'phone',
            'phone' => $request->session()->get('otp_phone'),
            'isNewAccount' => $request->session()->get('otp_is_new_account', false),
            'googleConfigured' => filled(config('services.google.client_id')),
            'title' => 'Sign in or create an account',
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): RedirectResponse
    {
        $phone = $request->string('phone')->toString();
        $this->otp->requestCode($phone);

        $isNewAccount = ! User::query()->where('phone', $phone)->exists();

        $request->session()->put('otp_phone', $phone);
        $request->session()->put('otp_is_new_account', $isNewAccount);

        return redirect()->route('web.login');
    }

    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $phone = $request->string('phone')->toString();

        if (! $this->otp->verifyCode($phone, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $request->string('name')->toString(),
                'provider' => 'phone',
                'provider_id' => $phone,
            ],
        );

        if ($user->isBanned()) {
            throw ValidationException::withMessages(['code' => 'This account has been suspended.']);
        }

        $request->session()->forget(['otp_phone', 'otp_is_new_account']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('web.account.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.home');
    }
}
