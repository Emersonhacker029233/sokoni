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

    /**
     * Only two safe, named destinations are ever accepted here — never a
     * raw URL/path — so a `?redirect=` query param can't be turned into an
     * open redirect (e.g. `?redirect=https://evil.example`). This is what
     * lets `redirect()->intended(...)` in verifyOtp() send a visitor who
     * clicked "Sign in" from the Chats/Profile sign-in prompt back to the
     * page they actually wanted, instead of always landing on the
     * dashboard.
     */
    private const SAFE_REDIRECT_TARGETS = [
        'chats' => 'web.account.messages',
        'profile' => 'web.account.dashboard',
    ];

    public function show(Request $request): View
    {
        if ($request->filled('redirect') && isset(self::SAFE_REDIRECT_TARGETS[$request->string('redirect')->toString()])) {
            $request->session()->put('url.intended', route(self::SAFE_REDIRECT_TARGETS[$request->string('redirect')->toString()]));
        }

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
        // Unlike the API, the website already has a real, reliable locale
        // for this request (SetWebLocale, the EN/SW toggle) — use it,
        // rather than the API's own "trust the client to say" fallback.
        $this->otp->requestCode($phone, app()->getLocale());

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
                'marketing_consent' => $request->boolean('marketing_consent'),
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
