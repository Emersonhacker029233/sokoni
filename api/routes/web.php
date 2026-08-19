<?php

use App\Http\Controllers\Web\Account\MessagesController;
use App\Http\Controllers\Web\Account\OrdersController;
use App\Http\Controllers\Web\Account\SavedController;
use App\Http\Controllers\Web\Account\SettingsController;
use App\Http\Controllers\Web\Account\ShopDashboardController;
use App\Http\Controllers\Web\Account\ShopProductsController;
use App\Http\Controllers\Web\Auth\GoogleAuthController;
use App\Http\Controllers\Web\Auth\IntentController;
use App\Http\Controllers\Web\Auth\OtpAuthController;
use App\Http\Controllers\Web\Auth\TermsAcceptanceController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LeadController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketplace website (sokoni.co.tz) — server-rendered, sharing the
| same app/database/models as the API. See CLAUDE.md's website brief for
| why every one of these must be indexable HTML, not client-rendered.
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('web.home');

Route::get('/c/{category}/{child?}', CategoryController::class)->name('web.category');
Route::get('/search', SearchController::class)->name('web.search');

Route::get('/p/{product}/{slug?}', ProductController::class)->name('web.product');
Route::post('/p/{product}/reveal-call', [LeadController::class, 'revealCall'])->name('web.product.reveal-call');

Route::get('/@{handle}', ShopController::class)->name('web.shop');

Route::get('/about', [PageController::class, 'about'])->name('web.about');
Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('web.how-it-works');
Route::get('/safety', [PageController::class, 'safety'])->name('web.safety');
Route::get('/terms', [PageController::class, 'terms'])->name('web.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('web.privacy');
Route::get('/contact', [PageController::class, 'contact'])->name('web.contact');
Route::get('/sell', [PageController::class, 'sell'])->name('web.sell');

Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('web.sitemap');
Route::get('/sitemap-products.xml', [SeoController::class, 'sitemapProducts'])->name('web.sitemap.products');
Route::get('/sitemap-shops.xml', [SeoController::class, 'sitemapShops'])->name('web.sitemap.shops');
Route::get('/sitemap-categories.xml', [SeoController::class, 'sitemapCategories'])->name('web.sitemap.categories');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('web.robots');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [OtpAuthController::class, 'show'])->name('web.login');
    Route::post('/auth/otp/request', [OtpAuthController::class, 'requestOtp'])->name('web.auth.otp.request')->middleware('throttle:otp');
    Route::post('/auth/otp/verify', [OtpAuthController::class, 'verifyOtp'])->name('web.auth.otp.verify');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('web.auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('web.auth.google.callback');
});

Route::middleware(['auth:web', 'web.onboarded'])->group(function () {
    Route::post('/logout', [OtpAuthController::class, 'logout'])->name('web.logout');

    Route::get('/auth/terms', [TermsAcceptanceController::class, 'show'])->name('web.auth.terms');
    Route::post('/auth/terms', [TermsAcceptanceController::class, 'store'])->name('web.auth.terms.store');

    Route::get('/auth/intent', [IntentController::class, 'show'])->name('web.auth.intent');
    Route::post('/auth/intent', [IntentController::class, 'store'])->name('web.auth.intent.store');

    Route::get('/account', [OrdersController::class, 'dashboard'])->name('web.account.dashboard');

    Route::get('/account/orders', [OrdersController::class, 'index'])->name('web.account.orders');
    Route::get('/account/orders/{order}', [OrdersController::class, 'show'])->name('web.account.orders.show');

    Route::get('/account/saved', SavedController::class)->name('web.account.saved');

    Route::get('/account/messages', [MessagesController::class, 'index'])->name('web.account.messages');
    Route::get('/account/messages/{conversation}', [MessagesController::class, 'show'])->name('web.account.messages.show');
    Route::post('/account/messages/{conversation}', [MessagesController::class, 'store'])->name('web.account.messages.store');
    Route::get('/account/messages/{conversation}/poll', [MessagesController::class, 'poll'])->name('web.account.messages.poll');

    Route::get('/account/settings', [SettingsController::class, 'edit'])->name('web.account.settings');
    Route::post('/account/settings', [SettingsController::class, 'update'])->name('web.account.settings.update');

    Route::get('/account/shop', ShopDashboardController::class)->name('web.account.shop');
    Route::get('/account/shop/products', [ShopProductsController::class, 'index'])->name('web.account.shop.products');
    Route::get('/account/shop/products/create', [ShopProductsController::class, 'create'])->name('web.account.shop.products.create');
    Route::post('/account/shop/products', [ShopProductsController::class, 'store'])->name('web.account.shop.products.store');
    Route::get('/account/shop/products/{product}/edit', [ShopProductsController::class, 'edit'])->name('web.account.shop.products.edit');
    Route::put('/account/shop/products/{product}', [ShopProductsController::class, 'update'])->name('web.account.shop.products.update');
});
