<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductMediaController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SellerDashboardController;
use App\Http\Controllers\Api\SellerProfileController;
use App\Http\Controllers\Api\ShowcaseController;
use App\Http\Controllers\Api\UpdateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes — browsing works fully without an account (CLAUDE.md
| feature 4: auth is only required at order, chat, or favourite).
|--------------------------------------------------------------------------
*/

Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
Route::post('/auth/social', [AuthController::class, 'socialLogin']);

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/products/{product}/comments', [CommentController::class, 'index']);

Route::get('/feed', [FeedController::class, 'index']);

Route::get('/sellers', [SellerProfileController::class, 'index']);
Route::get('/sellers/{handle}', [SellerProfileController::class, 'show']);
Route::get('/sellers/{handle}/reviews', [ReviewController::class, 'forSeller']);

// The social business layer (CLAUDE.md Part 3) — Updates/Offers/Showcases
// are always attached to a real, verified seller (see each migration's
// non-nullable seller_id FK), so browsing them is public the same as
// products/sellers themselves.
Route::get('/updates', [UpdateController::class, 'index']);
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/showcases', [ShowcaseController::class, 'index']);
Route::get('/showcases/{showcase}', [ShowcaseController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/terms/accept', [AuthController::class, 'acceptTerms']);
    Route::post('/auth/intent', [AuthController::class, 'updateIntent']);

    Route::post('/devices', [DeviceController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Seller onboarding wizard (CLAUDE.md feature 4).
    Route::post('/sellers', [SellerProfileController::class, 'store']);
    Route::patch('/sellers/{seller}/location', [SellerProfileController::class, 'updateLocation']);
    Route::patch('/sellers/{seller}/identity', [SellerProfileController::class, 'updateIdentity']);
    Route::patch('/sellers/{seller}/licence', [SellerProfileController::class, 'updateLicence']);
    Route::patch('/sellers/{seller}/logo', [SellerProfileController::class, 'updateLogo']);
    Route::patch('/sellers/{seller}', [SellerProfileController::class, 'update']);
    Route::get('/sellers/{seller}/dashboard', [SellerDashboardController::class, 'show']);

    Route::get('/shop/products', [ProductController::class, 'mine']);

    Route::middleware('throttle:api-write')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::patch('/products/{product}', [ProductController::class, 'update']);
        Route::patch('/products/{product}/boost', [ProductController::class, 'boost']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/media', [ProductMediaController::class, 'store']);
        Route::delete('/products/{product}/media/{media}', [ProductMediaController::class, 'destroy']);
        Route::post('/products/{product}/comments', [CommentController::class, 'store']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    });

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/products/{product}/favorite', [FavoriteController::class, 'store']);
    Route::delete('/products/{product}/favorite', [FavoriteController::class, 'destroy']);

    // Follow/unfollow — cheap, idempotent toggles, same reasoning as favorites above.
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/sellers/{handle}/follow', [CustomerController::class, 'store']);
    Route::delete('/sellers/{handle}/follow', [CustomerController::class, 'destroy']);

    Route::middleware('throttle:api-write')->group(function () {
        Route::post('/updates', [UpdateController::class, 'store']);
        Route::delete('/updates/{update}', [UpdateController::class, 'destroy']);
        Route::post('/offers', [OfferController::class, 'store']);
        Route::delete('/offers/{offer}', [OfferController::class, 'destroy']);
        Route::post('/showcases', [ShowcaseController::class, 'store']);
        Route::delete('/showcases/{showcase}', [ShowcaseController::class, 'destroy']);
    });

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/shop/orders', [OrderController::class, 'shopOrders']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/review', [ReviewController::class, 'store']);

    Route::patch('/reviews/{review}/reply', [ReviewController::class, 'reply']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::patch('/conversations/{conversation}/typing', [ConversationController::class, 'typing']);
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

    Route::post('/reports', [ReportController::class, 'store']);
});
