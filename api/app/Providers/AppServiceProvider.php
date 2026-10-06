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
use App\Services\Sms\KibonetSmsGateway;
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
                'kibonet' => new KibonetSmsGateway(
                    config('services.kibonet.api_key'),
                    config('services.kibonet.api_secret'),
                    config('services.kibonet.sender_id'),
                    config('services.kibonet.endpoint'),
                    config('services.kibonet.number_format'),
                    config('services.kibonet.delivery_report_url'),
                ),
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
        // Budget for the `api` middleware group's built-in throttle:api —
        // registered here in case anything opts into it later, but NOT
        // currently applied to any route: `bootstrap/app.php` never calls
        // `$middleware->throttleApi(...)`, and Laravel's own default for
        // that (`$apiLimiter`) is null, not 'api' — confirmed via
        // `php artisan route:list -vv`, which is what actually settled
        // the "Too Many Attempts" bug (client feedback) this file's other
        // otp-* limiters were written for: not this one stacking on top
        // of anything, as first suspected, but checkPhone() sharing a
        // budget with requestOtp() that it had no business sharing.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 3 OTP *sends* per phone number per 15 minutes — the phone is the
        // resource being protected against SMS-bombing (and, with a real
        // paid gateway now wired up, against burning the client's prepaid
        // SMS credit), not the requester. Applied ONLY to the route that
        // actually sends an SMS (requestOtp) — see the 'otp-check' note
        // just below for why the other auth routes must NOT share this
        // same budget, even though they're also keyed by phone.
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinutes(15, 3)->by($request->input('phone', $request->ip()));
        });

        // Bug (client feedback): "Adding another account leads correctly
        // into registration, but requesting the verification code fails
        // with Too Many Attempts." Root cause: checkPhone() — called
        // automatically, debounced, every time the phone field's value
        // settles while typing (step2_details.dart) — used to share the
        // exact same 'otp' limiter and bucket as requestOtp() above, even
        // though it never sends an SMS. Pausing mid-number, correcting a
        // digit, or re-checking a number the user wasn't sure of could
        // each fire another checkPhone() call for that same number — on a
        // second account someone types more carefully than their own
        // memorised number, three or four of those before ever tapping
        // "Send code" was enough to exhaust the 3-per-15-minutes budget
        // that route was never supposed to be spending. Kept separate,
        // and considerably more generous, since checking availability
        // costs nothing and has no reason to share a budget that exists
        // specifically to protect SMS spend.
        RateLimiter::for('otp-check', function (Request $request) {
            return Limit::perMinutes(15, 20)->by($request->input('phone', $request->ip()));
        });

        // otp/verify and register() don't send an SMS either, but a
        // phone-keyed ceiling is still worth having here specifically
        // against brute-forcing the 6-digit code itself — generous enough
        // that mistyping it a couple of times, or a legitimate resend
        // requiring a second verify, never trips it.
        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinutes(15, 10)->by($request->input('phone', $request->ip()));
        });

        // A real per-IP ceiling for the auth routes as a whole — not the
        // root cause found above, but "a sensible IP-level ceiling to
        // prevent abuse, set high enough that normal use never reaches
        // it" was asked for independently: one connection hammering many
        // *different* phone numbers should still eventually be stopped,
        // even though each individual number's own budget looks fine.
        RateLimiter::for('otp-ip', function (Request $request) {
            return Limit::perMinutes(15, 30)->by($request->ip());
        });

        // Writes (POST/PATCH/PUT/DELETE) get a tighter budget than reads.
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Username/password rework (CLAUDE.md Part D 2.8) — keyed on the
        // submitted `login` string (lowercased so "Amina"/"amina" share a
        // budget), not the resolved account, so a nonexistent username
        // still consumes the same bucket a real one would rather than
        // getting an unlimited budget of its own.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(15, 5)->by(strtolower((string) $request->input('login')).'|'.$request->ip());
        });

        // The SMS-code step of login — generous like otp-verify above,
        // for the same reason (a mistyped code or a legitimate resend
        // must not trip this).
        RateLimiter::for('login-verify', function (Request $request) {
            return Limit::perMinutes(15, 10)->by(strtolower((string) $request->input('login')).'|'.$request->ip());
        });

        // Forgot-password's code-sending step actually sends a real SMS,
        // so it gets the same tight budget `otp` itself uses, for the
        // same reason (protects the phone and the SMS credit alike).
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinutes(15, 3)->by(strtolower((string) $request->input('login')).'|'.$request->ip());
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
                'logoRemoveFailed' => __('site.js_logo_remove_failed'),
                'photoProcessFailed' => __('site.js_photo_process_failed'),
                'uploadFailed' => __('site.js_upload_failed'),
                'networkError' => __('site.js_network_error'),
            ]);
        });
    }
}
