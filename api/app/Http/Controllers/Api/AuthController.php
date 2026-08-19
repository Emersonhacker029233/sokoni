<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidSocialTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTermsRequest;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\SocialLoginRequest;
use App\Http\Requests\UpdateIntentRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Otp\PhoneOtpService;
use App\Services\SocialAuth\SocialAuthVerifier;
use App\Services\SocialAuth\SocialUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $this->otp->requestCode($request->string('phone'));

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
