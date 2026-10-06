<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckUsernameRequest;
use App\Http\Requests\ForgotPasswordRequestRequest;
use App\Http\Requests\ForgotPasswordResetRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SetCredentialsRequest;
use App\Http\Requests\UpdateTwoFactorRequest;
use App\Http\Requests\VerifyLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Otp\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Username/password sign-in (CLAUDE.md Part D) — replaces send-code-first
 * as the primary path. The old phone+code flow (AuthController::requestOtp/
 * verifyOtp) stays fully available and untouched for accounts that haven't
 * set a password yet (`User::needsCredentialSetup()`), per 2.6: "Keep old
 * phone-and-code path available until all accounts migrate."
 *
 * Enumeration safety (2.8) runs through every method here: a nonexistent
 * username/phone, an admin account (never reachable through these public
 * routes at all), and a real account with a wrong password all produce the
 * exact same response — same message, same shape, same rate-limit bucket.
 * `password.null` (an unmigrated account) is folded into that same generic
 * failure too, not given its own message, since a distinct message would
 * itself leak "this username exists" to anyone probing random usernames.
 */
class PasswordAuthController extends Controller
{
    /**
     * A real bcrypt hash of an arbitrary string, hardcoded (not computed
     * per-request) so `Hash::check()` always does the same amount of work
     * whether or not an account was actually found — closing the timing
     * side-channel a short-circuit (`$user === null` skipping the hash
     * check entirely) would otherwise open.
     */
    private const DUMMY_HASH = '$2y$12$/ESYy4cc8jPTLdS0pNYS3.lUv2qiJdT6TY9CfUzZgWCmZDQngxjji';

    public function __construct(private readonly PhoneOtpService $otp) {}

    public function checkUsername(CheckUsernameRequest $request): JsonResponse
    {
        $username = $request->string('username')->lower()->toString();

        $available = ! in_array($username, User::RESERVED_USERNAMES, true)
            && ! User::query()->where('username', $username)->exists();

        return response()->json(['available' => $available]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->resolveLogin($request->string('login')->toString());
        $passwordMatches = Hash::check($request->string('password'), $user?->password ?? self::DUMMY_HASH);

        if ($user === null || $user->password === null || ! $passwordMatches) {
            throw ValidationException::withMessages(['login' => 'Invalid username/phone or password.']);
        }

        if ($user->isBanned()) {
            throw ValidationException::withMessages(['login' => 'This account has been suspended.']);
        }

        $deviceToken = $request->string('device_token')->toString();
        $trustedDevice = $deviceToken !== ''
            ? TrustedDevice::find($deviceToken)
            : null;
        $isRecognisedDevice = $trustedDevice !== null && $trustedDevice->user_id === $user->id;

        if ($isRecognisedDevice && ! $user->two_factor_enabled) {
            $trustedDevice->touchLastUsed();

            return $this->issueToken($user);
        }

        // Not a recognised device, or two-factor is explicitly on — a code
        // is now something that happens AFTER a password, never instead
        // of one (CLAUDE.md 2.2).
        $expiresAt = $this->otp->requestCode($user->phone, $user->locale ?? 'en');

        return response()->json([
            'requires_code' => true,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /** Completes a login() call that returned `requires_code: true`. Issues a fresh "recognised device" token so the next sign-in from this same device can skip this step. */
    public function verifyLogin(VerifyLoginRequest $request): JsonResponse
    {
        $user = $this->resolveLogin($request->string('login')->toString());

        if ($user === null || ! $this->otp->verifyCode($user->phone, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        if ($user->isBanned()) {
            throw ValidationException::withMessages(['code' => 'This account has been suspended.']);
        }

        $deviceToken = TrustedDevice::issueFor($user, $request->userAgent());

        return $this->issueToken($user, extra: ['device_token' => $deviceToken]);
    }

    /** CLAUDE.md 2.6 — an existing account's one-time upgrade to a username+password, or a brand-new account's first time setting one. */
    /**
     * One-time only — a stolen bearer token must not be enough to
     * silently swap a password that already exists, with no SMS code and
     * no proof of the old one. Once a password is set, changing it goes
     * through the SMS-verified forgot-password flow instead, same as any
     * other password change.
     */
    public function setCredentials(SetCredentialsRequest $request): UserResource
    {
        $user = $request->user();

        if (! $user->needsCredentialSetup()) {
            throw ValidationException::withMessages([
                'username' => 'Your account already has a username and password — use "Forgot password?" to change it.',
            ]);
        }

        $user->update([
            'username' => $request->string('username')->lower()->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        return new UserResource($user->fresh());
    }

    public function updateTwoFactor(UpdateTwoFactorRequest $request): UserResource
    {
        $user = $request->user();
        $user->update(['two_factor_enabled' => $request->boolean('enabled')]);

        return new UserResource($user->fresh());
    }

    /**
     * CLAUDE.md 2.5 — always the exact same response whether or not the
     * account exists, so this alone can never confirm a username/phone is
     * registered. Only a real, existing, non-admin account ever actually
     * receives an SMS.
     */
    public function forgotPasswordRequest(ForgotPasswordRequestRequest $request): JsonResponse
    {
        $user = $this->resolveLogin($request->string('login')->toString());

        if ($user !== null) {
            $this->otp->requestCode($user->phone, $user->locale ?? 'en');
        }

        return response()->json(['message' => 'If that account exists, a verification code has been sent.']);
    }

    /** Step 2 — the code plus the new password in one request. Revokes every existing session (CLAUDE.md 2.5/2.8), then signs this request in fresh. */
    public function forgotPasswordReset(ForgotPasswordResetRequest $request): JsonResponse
    {
        $user = $this->resolveLogin($request->string('login')->toString());

        if ($user === null || ! $this->otp->verifyCode($user->phone, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        if ($user->isBanned()) {
            throw ValidationException::withMessages(['code' => 'This account has been suspended.']);
        }

        $user->tokens()->delete();
        $user->trustedDevices()->delete();
        $user->update(['password' => $request->string('password')->toString()]);

        $deviceToken = TrustedDevice::issueFor($user, $request->userAgent());

        return $this->issueToken($user, extra: ['device_token' => $deviceToken]);
    }

    /**
     * `$login` is either a username or an E.164 phone — tried as a phone
     * first only when it actually looks like one, so a username that
     * happens to be all digits is never misread as a phone number.
     * Admin/staff accounts are excluded entirely (CLAUDE.md 2.8: "cannot
     * be migrated or reset through public flows") — treated identically
     * to a nonexistent account by every caller above.
     */
    private function resolveLogin(string $login): ?User
    {
        $column = preg_match('/^\+255[67]\d{8}$/', $login) === 1 ? 'phone' : 'username';
        $value = $column === 'username' ? mb_strtolower($login) : $login;

        return User::query()->where($column, $value)->where('is_admin', false)->first();
    }

    private function issueToken(User $user, array $extra = []): JsonResponse
    {
        $token = $user->createToken('sokoni-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            ...$extra,
        ]);
    }
}
