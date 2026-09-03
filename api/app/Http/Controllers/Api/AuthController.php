<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidSocialTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTermsRequest;
use App\Http\Requests\RegisterAccountRequest;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\SocialLoginRequest;
use App\Http\Requests\UpdateIntentRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Otp\PhoneOtpService;
use App\Services\SocialAuth\SocialAuthVerifier;
use App\Services\SocialAuth\SocialUserResolver;
use App\Support\HandlesEmailChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly PhoneOtpService $otp,
        private readonly SocialAuthVerifier $socialVerifier,
        private readonly SocialUserResolver $socialUserResolver,
    ) {}

    /**
     * Send (log, in dev) a 6-digit OTP to the given Tanzanian phone number.
     * `is_new_account` lets the client say plainly, right when the code is
     * sent, that a new account is about to be created — CLAUDE.md Part 2
     * item 1 — rather than only finding out after the code is verified.
     */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $this->otp->requestCode($request->string('phone'), $request->string('locale', 'en'));

        $isNewAccount = ! User::query()->where('phone', $request->string('phone'))->exists();

        return response()->json(['message' => 'OTP sent.', 'is_new_account' => $isNewAccount]);
    }

    /** Verify the OTP and issue a Sanctum token, creating the user on first sign-in. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        if (! $this->otp->verifyCode($request->string('phone'), $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $request->string('phone')],
            [
                'name' => $request->string('name'),
                'provider' => 'phone',
                'provider_id' => $request->string('phone'),
            ]
        );

        return $this->issueToken($user, $user->wasRecentlyCreated);
    }

    /**
     * Check-only — does this number already have an account? Unlike
     * requestOtp() above, no OTP is sent as a side effect of asking. Used
     * by the "Create an account" flow's details step to catch "this
     * number is already registered" while the user can still do something
     * about it, rather than discovering it at the final verify step after
     * filling in everything else (CLAUDE.md restructure, 2026-08-25:
     * "validate as you go, not at the end").
     */
    public function checkPhone(RequestOtpRequest $request): JsonResponse
    {
        $exists = User::query()->where('phone', $request->string('phone'))->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Final step of the "Create an account" flow — verifies the OTP and
     * creates the account (and, for a seller, the SellerProfile) in one
     * atomic step. Distinct from verifyOtp() below: that endpoint backs
     * the separate "Sign in" flow and only ever creates a bare User with
     * no seller profile. This one exists specifically so a race between
     * two people registering the same phone/handle — or a dropped
     * connection mid-request — can't leave a half-created account behind;
     * see the re-checks immediately before the transaction, closing most
     * of that window (the DB's own unique constraints on `users.phone`/
     * `seller_profiles.handle` are the actual last line of defence).
     */
    public function register(RegisterAccountRequest $request): JsonResponse
    {
        if (! $this->otp->verifyCode($request->string('phone'), $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        if (User::query()->where('phone', $request->string('phone'))->exists()) {
            throw ValidationException::withMessages(['phone' => 'This number already has an account — sign in instead.']);
        }

        $isSeller = $request->string('account_intent')->toString() === 'sell';

        if ($isSeller && SellerProfile::query()->where('handle', $request->string('handle'))->exists()) {
            throw ValidationException::withMessages(['handle' => 'This handle was just taken — please choose another.']);
        }

        $user = DB::transaction(function () use ($request, $isSeller) {
            $user = User::create([
                'phone' => $request->string('phone'),
                'name' => $request->string('name'),
                'email' => $request->filled('email') ? $request->string('email')->toString() : null,
                'marketing_consent' => $request->boolean('marketing_consent'),
                'provider' => 'phone',
                'provider_id' => $request->string('phone'),
                'account_intent' => $request->string('account_intent'),
                'terms_accepted_at' => now(),
                'terms_version' => $request->string('terms_version'),
            ]);

            if ($isSeller) {
                $user->sellerProfile()->create([
                    'shop_name' => $request->string('shop_name'),
                    'handle' => $request->string('handle'),
                    'category_id' => $request->integer('category_id'),
                    'region' => $request->string('region'),
                    'district' => $request->string('district'),
                    'address' => $request->string('address'),
                    'whatsapp' => $request->filled('whatsapp') ? $request->string('whatsapp') : null,
                ]);
            }

            return $user;
        });

        if ($user->email !== null) {
            $user->sendEmailVerificationNotification();
        }

        return $this->issueToken($user, true);
    }

    /** Verify a Google/Apple token server-side and issue a Sanctum token. */
    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        try {
            $identity = $this->socialVerifier->verify(
                $request->string('provider'),
                $request->string('token'),
            );
        } catch (InvalidSocialTokenException $e) {
            throw ValidationException::withMessages(['token' => $e->getMessage()]);
        }

        ['user' => $user, 'isNewAccount' => $isNewAccount] = $this->socialUserResolver->resolve($identity);

        return $this->issueToken($user, $isNewAccount);
    }

    /**
     * One-time "buy / sell / decide later" intent, shown client-side only
     * right after a brand-new account's first sign-in — CLAUDE.md Part 2
     * item 2. Presentation-only: does not create a seller_profile or
     * change anything about the account model, just records what the
     * onboarding screen should show (or skip) from here on.
     */
    public function updateIntent(UpdateIntentRequest $request): UserResource
    {
        $user = $request->user();
        $user->update(['account_intent' => $request->string('intent')]);

        return new UserResource($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * C5: name/email/locale from the app's profile settings screen — the
     * same `UpdateProfileRequest` and `HandlesEmailChange` the website's
     * settings page uses, so a changed email always gets the same
     * reset-and-resend-verification treatment regardless of which client
     * changed it.
     */
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        $user->update([
            'name' => $request->string('name')->toString(),
            ...($request->has('locale') ? ['locale' => $request->string('locale')->toString()] : []),
        ]);
        HandlesEmailChange::apply($user, $request->filled('email') ? $request->string('email')->toString() : null);

        return new UserResource($user->fresh());
    }

    /** A signed-in user with an unverified email can ask for the link again. */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /** Record Terms & Privacy acceptance with a timestamp + version, per CLAUDE.md feature 11. */
    public function acceptTerms(AcceptTermsRequest $request): UserResource
    {
        $user = $request->user();
        $user->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => $request->string('version'),
        ])->save();

        return new UserResource($user);
    }

    private function issueToken(User $user, bool $isNewAccount = false): JsonResponse
    {
        if ($user->isBanned()) {
            throw ValidationException::withMessages(['phone' => 'This account has been suspended.']);
        }

        $token = $user->createToken('sokoni-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            'is_new_account' => $isNewAccount,
        ]);
    }
}
