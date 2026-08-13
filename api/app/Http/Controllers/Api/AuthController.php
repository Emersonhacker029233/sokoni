<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidSocialTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTermsRequest;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\SocialLoginRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Otp\PhoneOtpService;
use App\Services\SocialAuth\SocialAuthVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly PhoneOtpService $otp,
        private readonly SocialAuthVerifier $socialVerifier,
    ) {}

    /** Send (log, in dev) a 6-digit OTP to the given Tanzanian phone number. */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $this->otp->requestCode($request->string('phone'));

        return response()->json(['message' => 'OTP sent.']);
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

        return $this->issueToken($user);
    }

    /** Verify a Google/Facebook/Apple token server-side and issue a Sanctum token. */
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

        $user = User::query()->where('provider', $identity->provider)
            ->where('provider_id', $identity->providerId)
            ->first();

        if (! $user && $identity->email) {
            $user = User::query()->where('email', $identity->email)->first();
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => $identity->name ?? 'Sokoni User',
                'email' => $identity->email,
                'avatar' => $identity->avatar,
                'provider' => $identity->provider,
                'provider_id' => $identity->providerId,
            ]);
        } elseif (! $user->provider) {
            $user->forceFill([
                'provider' => $identity->provider,
                'provider_id' => $identity->providerId,
            ])->save();
        }

        return $this->issueToken($user);
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

    private function issueToken(User $user): JsonResponse
    {
        if ($user->isBanned()) {
            throw ValidationException::withMessages(['phone' => 'This account has been suspended.']);
        }

        $token = $user->createToken('sokoni-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
