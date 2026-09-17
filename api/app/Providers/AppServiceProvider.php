<?php

namespace App\Providers;

use App\Services\Nida\ManualReviewNidaVerifier;
use App\Services\Nida\NidaVerifier;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\UnimplementedPaymentGateway;
use App\Services\Push\LogPushNotifier;
use App\Services\Push\PushNotifier;
use App\Services\Sms\AfricasTalkingSmsGateway;
use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\TextifySmsGateway;
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
        // Driver-selected, not credential-sniffed: SMS_DRIVER picks the
        // gateway explicitly rather than inferring it from which keys
        // happen to be set, so there's never a question of which provider
        // is actually live in a given environment. Unset/unrecognised
        // always falls back to LogSmsGateway (local dev, CI) — see
        // docs/SMS.md.
        $this->app->bind(SmsGateway::class, function () {
            return match (config('services.sms_driver')) {
                'textify' => new TextifySmsGateway(
                    config('services.textify.api_key'),
                    config('services.textify.sender_name'),
                    config('services.textify.endpoint'),
                ),
                'africastalking' => new AfricasTalkingSmsGateway(
                    config('services.africastalking.username'),
                    config('services.africastalking.api_key'),
                    config('services.africastalking.sender_id'),
                    (bool) config('services.africastalking.sandbox'),
                ),
                'beem' => new BeemSmsGateway(
                    config('services.beem.api_key'),
                    config('services.beem.secret_key'),
                    config('services.beem.sender_id'),
                ),
                default => new LogSmsGateway,
            };
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

        // 3 OTP requests per phone number per 15 minutes — the phone is the
        // resource being protected against SMS-bombing (and, with a real
        // paid gateway now wired up, against burning the client's prepaid
        // SMS credit), not the requester.
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinutes(15, 3)->by($request->input('phone', $request->ip()));
        });

        // Writes (POST/PATCH/PUT/DELETE) get a tighter budget than reads.
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Every web page's header needs the category nav — one composer
        // rather than every Web controller passing it explicitly.
        View::composer(['partials.header', 'partials.footer'], function ($view) {
            $view->with('navCategories', app(CategoryCatalogService::class)->withCounts());
        });

        // The mega menu's full tree (with subcategories) — the header only,
        // since it's the only surface that renders it.
        View::composer('partials.header', function ($view) {
            $view->with('megaMenuTree', app(CategoryCatalogService::class)->megaMenuTree());
        });

        // The mobile bottom nav's unread badge on Chats — only worth
        // querying for a signed-in visitor, never for the vastly more
        // common signed-out page view.
        View::composer(['partials.header', 'partials.bottom-nav', 'layouts.app'], function ($view) {
            $user = auth('web')->user();
            $view->with('unreadMessagesCount', $user ? \App\Support\UnreadMessages::countFor($user) : 0);
        });

        // B3 (tester feedback): the header notification bell's initial
        // count — same signed-in-only reasoning as unreadMessagesCount
        // above, and the same "server-rendered initial value, then live
        // polling takes over" pattern (see Alpine.data('notificationBell')).
        View::composer(['partials.header', 'layouts.app'], function ($view) {
            $user = auth('web')->user();
            $view->with('unreadNotificationsCount', $user ? $user->appNotifications()->unread()->count() : 0);
        });

        Paginator::defaultView('vendor.pagination.sokoni');
        Paginator::defaultSimpleView('vendor.pagination.sokoni');

        // Language audit (client feedback): resources/js/app.js is a
        // plain compiled asset that can't call the translator itself —
        // every user-facing string it needs goes through this bridge
        // instead of being hardcoded English in the script.
        View::composer('layouts.app', function ($view) {
            $view->with('sokoniI18n', [
                'locale' => app()->getLocale(),
                'newMessageToast' => __('site.js_new_message_toast'),
                'locationUnavailable' => __('site.js_location_unavailable'),
                'locationPermissionDenied' => __('site.js_location_permission_denied'),
                'locationOtherError' => __('site.js_location_other_error'),
                'locationFilledIn' => __('site.js_location_filled_in'),
                'locationNoAddress' => __('site.js_location_no_address'),
                'logoUpdateFailed' => __('site.js_logo_update_failed'),
                'photoProcessFailed' => __('site.js_photo_process_failed'),
                'uploadFailed' => __('site.js_upload_failed'),
                'networkError' => __('site.js_network_error'),
            ]);
        });
    }
}
