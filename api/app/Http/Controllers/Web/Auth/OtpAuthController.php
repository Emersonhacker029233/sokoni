<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\Auth\WebAccountSwitcher;
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
    public function __construct(
        private readonly PhoneOtpService $otp,
        private readonly WebAccountSwitcher $switcher,
    ) {}

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

    public function show(Request $request): RedirectResponse|View
    {
        // Part 5 (client feedback): "Add account" reaches this exact page
        // while already authenticated — this route is no longer behind
        // `guest:web` so that can work at all. A visitor who is already
        // signed in and did NOT arrive via "Add account" gets the same
        // bounce the old middleware used to give them.
        // Carried in session, not just the query string, because
        // `requestOtp()` redirects back here with a bare
        // `route('web.login')` (no query params) between the phone and
        // code steps — without this, the code step of an "Add account"
        // flow would look like a bare, non-adding /login visit and bounce
        // an already-authenticated visitor straight to the dashboard
        // before they could ever enter the code.
        if ($request->has('add_account')) {
            $request->session()->put('otp_adding_account', $request->boolean('add_account'));
        }
        $addingAccount = $request->session()->get('otp_adding_account', false);

        if (Auth::check() && ! $addingAccount) {
            return redirect()->intended(route('web.account.dashboard'));
        }

        if ($request->filled('redirect') && isset(self::SAFE_REDIRECT_TARGETS[$request->string('redirect')->toString()])) {
            $request->session()->put('url.intended', route(self::SAFE_REDIRECT_TARGETS[$request->string('redirect')->toString()]));
        }

        return view('web.auth.login', [
            'addingAccount' => $addingAccount,
            'step' => $request->session()->get('otp_phone') ? 'code' : 'phone',
            'phone' => $request->session()->get('otp_phone'),
            'isNewAccount' => $request->session()->get('otp_is_new_account', false),
            // Part 3 (client feedback): "show when the current code
            // expires" / "clear feedback that a new code has been sent".
            'otpExpiresAt' => $request->session()->get('otp_expires_at'),
            'otpJustResent' => $request->session()->pull('otp_just_resent', false),
            // C1 (tester feedback): this used to check only `client_id`,
            // but `GoogleAuthController::redirect()` 404s unless
            // client_id + client_secret + redirect are ALL set — a config
            // with just client_id set (e.g. only the app's native flow
            // configured) would have shown a button that then 404'd.
            // Delegate to the controller's own check so there's exactly
            // one definition of "configured" for this flow.
            // C1 (tester feedback): this used to check only `client_id`,
            // but `GoogleAuthController::redirect()` 404s unless
            // client_id + client_secret + redirect are ALL set — a config
            // with just client_id set (e.g. only the app's native flow
            // configured) would have shown a button that then 404'd.
            // Delegate to the controller's own check so there's exactly
            // one definition of "configured" for this flow.
            'googleConfigured' => GoogleAuthController::isConfigured(),
            'title' => 'Sign in or create an account',
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): RedirectResponse
    {
        $phone = $request->string('phone')->toString();
        // Distinguish "resending to the same number already on the code
        // step" from "starting fresh from the phone step" purely from
        // session state already there before this request touches it —
        // this endpoint is the exact same one the code step's own Resend
        // button posts back to, deliberately (see login.blade.php),
        // rather than a separate resend-only route.
        $isResend = $request->session()->get('otp_phone') === $phone;

        // Unlike the API, the website already has a real, reliable locale
        // for this request (SetWebLocale, the EN/SW toggle) — use it,
        // rather than the API's own "trust the client to say" fallback.
        $expiresAt = $this->otp->requestCode($phone, app()->getLocale());

        $isNewAccount = ! User::query()->where('phone', $phone)->exists();

        $request->session()->put('otp_phone', $phone);
        $request->session()->put('otp_is_new_account', $isNewAccount);
        // Stored as a plain ISO8601 string, not the Carbon instance
        // itself — avoids relying on session-driver-specific object
        // serialization for something that's only ever read back as a
        // string to hand to the view/Alpine anyway.
        $request->session()->put('otp_expires_at', $expiresAt->toIso8601String());
        if ($isResend) {
            $request->session()->flash('otp_just_resent', true);
        }

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

        $request->session()->forget(['otp_phone', 'otp_is_new_account', 'otp_expires_at', 'otp_adding_account']);

        $this->switcher->login($request, $user);

        return redirect()->intended(route('web.account.dashboard'));
    }

    /**
     * Part 5 (client feedback): "Signing out removes only the active
     * account and returns to the next one, or to guest if it was the
     * last." See WebAccountSwitcher::signOutActive() for the mechanics.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->switcher->signOutActive($request);

        return redirect()->route('web.home');
    }
}
