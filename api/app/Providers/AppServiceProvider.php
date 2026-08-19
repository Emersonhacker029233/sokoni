<?php

namespace App\Providers;

use App\Services\Nida\ManualReviewNidaVerifier;
use App\Services\Nida\NidaVerifier;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\UnimplementedPaymentGateway;
use App\Services\Push\LogPushNotifier;
use App\Services\Push\PushNotifier;
use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\SocialAuth\HttpSocialAuthVerifier;
use App\Services\SocialAuth\SocialAuthVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // MOCK bindings — see BLOCKERS.md. Swap these for real
        // implementations as credentials/agreements land.
        $this->app->bind(SmsGateway::class, function () {
            $apiKey = config('services.beem.api_key');
            $secretKey = config('services.beem.secret_key');

            if ($apiKey && $secretKey) {
                return new BeemSmsGateway($apiKey, $secretKey, config('services.beem.sender_id'));
            }

            // No Beem credentials configured (local dev, CI) — log the
            // code instead of sending it. See docs/SMS.md.
            return new LogSmsGateway;
        });
        $this->app->bind(NidaVerifier::class, ManualReviewNidaVerifier::class);
        $this->app->bind(PaymentGateway::class, UnimplementedPaymentGateway::class);
        $this->app->bind(SocialAuthVerifier::class, HttpSocialAuthVerifier::class);
        $this->app->bind(PushNotifier::class, LogPushNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Default budget for the `api` middleware group's built-in throttle:api.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 3 OTP requests per phone number per 10 minutes — the phone is the
        // resource being protected against SMS-bombing, not the requester.
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->input('phone', $request->ip()));
        });

        // Writes (POST/PATCH/PUT/DELETE) get a tighter budget than reads.
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Every web page's header needs the category nav — one composer
        // rather than every Web controller passing it explicitly.
        View::composer('partials.header', function ($view) {
            $view->with('navCategories', app(CategoryCatalogService::class)->withCounts());
        });

        Paginator::defaultView('vendor.pagination.sokoni');
        Paginator::defaultSimpleView('vendor.pagination.sokoni');
    }
}
