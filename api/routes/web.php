<?php

use App\Http\Controllers\Web\Account\AccountSwitchController;
use App\Http\Controllers\Web\Account\MessagesController;
use App\Http\Controllers\Web\Account\NotificationsController;
use App\Http\Controllers\Web\Account\OrdersController;
use App\Http\Controllers\Web\Account\ReportController;
use App\Http\Controllers\Web\Account\SavedController;
use App\Http\Controllers\Web\Account\SellerRegistrationController;
use App\Http\Controllers\Web\Account\SettingsController;
use App\Http\Controllers\Web\Account\ShopDashboardController;
use App\Http\Controllers\Web\Account\ShopHoursController;
use App\Http\Controllers\Web\Account\ShopLogoController;
use App\Http\Controllers\Web\Account\ShopProductMediaController;
use App\Http\Controllers\Web\Account\ShopProductsController;
use App\Http\Controllers\Web\Auth\GoogleAuthController;
use App\Http\Controllers\Web\Auth\IntentController;
use App\Http\Controllers\Web\Auth\OtpAuthController;
use App\Http\Controllers\Web\Auth\TermsAcceptanceController;
use App\Http\Controllers\Web\BannerClickController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DiagnosticLogController;
use App\Http\Controllers\Web\ExploreController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LeadController;
use App\Http\Controllers\Web\NavGateController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\ShopController;
use App\Http\Controllers\Web\StoresController;
use App\Http\Controllers\Web\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketplace website (sokoni.co.tz) — server-rendered, sharing the
| same app/database/models as the API. See CLAUDE.md's website brief for
| why every one of these must be indexable HTML, not client-rendered.
|--------------------------------------------------------------------------
*/

// TEMPORARY DIAGNOSTIC — delete this route plus
// app/Http/Controllers/Web/DiagnosticLogController.php once the
// production-only /c/{category} 500 is confirmed and fixed. Dead (always
// 404s) unless DIAGNOSTIC_LOG_TOKEN is set in .env.
Route::get('/_diagnostic/log', DiagnosticLogController::class)->name('diagnostic.log');

Route::get('/', HomeController::class)->name('web.home');

Route::get('/c/{category}/{child?}', CategoryController::class)->name('web.category');
Route::get('/search', SearchController::class)->name('web.search');
Route::get('/stores', StoresController::class)->name('web.stores');
Route::get('/explore', ExploreController::class)->name('web.explore');

// Public — not behind auth:web — so a signed-out visitor sees an
// explanatory sign-in prompt (per tester feedback) instead of an
// immediate, unexplained redirect to /login.
Route::get('/chats', [NavGateController::class, 'chats'])->name('web.chats');
Route::get('/profile', [NavGateController::class, 'profile'])->name('web.profile');

Route::get('/p/{product}/{slug?}', ProductController::class)->name('web.product');
Route::post('/p/{product}/reveal-call', [LeadController::class, 'revealCall'])->name('web.product.reveal-call');

Route::get('/@{handle}', ShopController::class)->name('web.shop');

Route::get('/banners/{banner}/click', BannerClickController::class)->name('web.banners.click');

Route::get('/about', [PageController::class, 'about'])->name('web.about');
Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('web.how-it-works');
Route::get('/safety', [PageController::class, 'safety'])->name('web.safety');
Route::get('/terms', [PageController::class, 'terms'])->name('web.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('web.privacy');
Route::get('/contact', [PageController::class, 'contact'])->name('web.contact');
Route::get('/sell', [PageController::class, 'sell'])->name('web.sell');

// Deliberately outside any auth group — see VerifyEmailController's own
// docblock for why the clicking browser doesn't need a live session.
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('web.sitemap');
Route::get('/sitemap-products.xml', [SeoController::class, 'sitemapProducts'])->name('web.sitemap.products');
Route::get('/sitemap-shops.xml', [SeoController::class, 'sitemapShops'])->name('web.sitemap.shops');
Route::get('/sitemap-categories.xml', [SeoController::class, 'sitemapCategories'])->name('web.sitemap.categories');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('web.robots');

// Part 5 (client feedback): NOT behind `guest:web` — "Add account" reaches
// this exact phone-OTP/Google flow while already signed in as someone
// else, so an already-authenticated visitor must still be able to load
// and post to these. `OtpAuthController::show()` does the old guest-gate
// bounce itself for anyone who ISN'T explicitly adding an account.
Route::get('/login', [OtpAuthController::class, 'show'])->name('web.login');
Route::post('/auth/otp/request', [OtpAuthController::class, 'requestOtp'])->name('web.auth.otp.request')->middleware('throttle:otp');
Route::post('/auth/otp/verify', [OtpAuthController::class, 'verifyOtp'])->name('web.auth.otp.verify');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('web.auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('web.auth.google.callback');

Route::middleware(['auth:web', 'web.onboarded'])->group(function () {
    Route::post('/logout', [OtpAuthController::class, 'logout'])->name('web.logout');
    Route::post('/account/switch/{targetUser}', [AccountSwitchController::class, 'switch'])->name('web.account.switch');

    Route::get('/auth/terms', [TermsAcceptanceController::class, 'show'])->name('web.auth.terms');
    Route::post('/auth/terms', [TermsAcceptanceController::class, 'store'])->name('web.auth.terms.store');

    Route::get('/auth/intent', [IntentController::class, 'show'])->name('web.auth.intent');
    Route::post('/auth/intent', [IntentController::class, 'store'])->name('web.auth.intent.store');

    Route::get('/account', [OrdersController::class, 'dashboard'])->name('web.account.dashboard');

    Route::get('/account/orders', [OrdersController::class, 'index'])->name('web.account.orders');
    Route::get('/account/orders/{order}', [OrdersController::class, 'show'])->name('web.account.orders.show');
    Route::post('/account/orders/{order}/review', [OrdersController::class, 'storeReview'])->name('web.account.orders.review');

    Route::post('/account/reports', [ReportController::class, 'store'])->name('web.account.reports.store');

    Route::get('/account/saved', SavedController::class)->name('web.account.saved');

    Route::get('/account/messages', [MessagesController::class, 'index'])->name('web.account.messages');
    // Must be declared before the {conversation} routes below — otherwise
    // "start" is captured by {conversation}'s route-model binding as a
    // literal id lookup and 404s before this action is ever reached.
    Route::post('/account/messages/start', [MessagesController::class, 'start'])->name('web.account.messages.start');
    Route::get('/account/messages/unread-count', [MessagesController::class, 'unreadCount'])->name('web.account.messages.unread-count');
    Route::get('/account/messages/{conversation}', [MessagesController::class, 'show'])->name('web.account.messages.show');
    Route::post('/account/messages/{conversation}', [MessagesController::class, 'store'])->name('web.account.messages.store');
    Route::get('/account/messages/{conversation}/poll', [MessagesController::class, 'poll'])->name('web.account.messages.poll');

    // B3 (tester feedback): the website's own notifications bell — see
    // NotificationsController's docblock.
    Route::get('/account/notifications', [NotificationsController::class, 'index'])->name('web.account.notifications');
    Route::get('/account/notifications/unread-count', [NotificationsController::class, 'unreadCount'])->name('web.account.notifications.unread-count');
    Route::get('/account/notifications/recent', [NotificationsController::class, 'recent'])->name('web.account.notifications.recent');
    Route::post('/account/notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('web.account.notifications.read-all');
    Route::post('/account/notifications/{notification}/read', [NotificationsController::class, 'markRead'])->name('web.account.notifications.read');

    Route::get('/account/settings', [SettingsController::class, 'edit'])->name('web.account.settings');
    Route::post('/account/settings', [SettingsController::class, 'update'])->name('web.account.settings.update');
    Route::post('/account/settings/resend-verification', [SettingsController::class, 'resendVerification'])->name('web.account.settings.resend-verification');
    Route::post('/account/shop/{seller}/logo', [ShopLogoController::class, 'update'])->name('web.account.shop.logo');
    Route::post('/account/shop/{seller}/hours', [ShopHoursController::class, 'update'])->name('web.account.shop.hours');

    Route::get('/account/shop/register', [SellerRegistrationController::class, 'show'])->name('web.account.shop.register');
    Route::post('/account/shop/register', [SellerRegistrationController::class, 'store'])->name('web.account.shop.register.store');

    Route::get('/account/shop', ShopDashboardController::class)->name('web.account.shop');
    Route::get('/account/shop/products', [ShopProductsController::class, 'index'])->name('web.account.shop.products');
    Route::get('/account/shop/products/create', [ShopProductsController::class, 'create'])->name('web.account.shop.products.create');
    Route::post('/account/shop/products', [ShopProductsController::class, 'store'])->name('web.account.shop.products.store');
    Route::get('/account/shop/products/{product}/edit', [ShopProductsController::class, 'edit'])->name('web.account.shop.products.edit');
    Route::put('/account/shop/products/{product}', [ShopProductsController::class, 'update'])->name('web.account.shop.products.update');

    Route::post('/account/shop/products/{product}/media', [ShopProductMediaController::class, 'store'])->name('web.account.shop.products.media.store');
    Route::delete('/account/shop/products/{product}/media/{media}', [ShopProductMediaController::class, 'destroy'])->name('web.account.shop.products.media.destroy');
    Route::post('/account/shop/products/{product}/media/reorder', [ShopProductMediaController::class, 'reorder'])->name('web.account.shop.products.media.reorder');
});
