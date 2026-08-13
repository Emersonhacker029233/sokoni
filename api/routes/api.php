<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SellerProfileController;
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

Route::get('/sellers', [SellerProfileController::class, 'index']);
Route::get('/sellers/{handle}', [SellerProfileController::class, 'show']);
Route::get('/sellers/{handle}/reviews', [ReviewController::class, 'forSeller']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/terms/accept', [AuthController::class, 'acceptTerms']);

    Route::post('/devices', [DeviceController::class, 'store']);

    // Seller onboarding wizard (CLAUDE.md feature 4).
    Route::post('/sellers', [SellerProfileController::class, 'store']);
    Route::patch('/sellers/{seller}/location', [SellerProfileController::class, 'updateLocation']);
    Route::patch('/sellers/{seller}/identity', [SellerProfileController::class, 'updateIdentity']);
    Route::patch('/sellers/{seller}/licence', [SellerProfileController::class, 'updateLicence']);
    Route::patch('/sellers/{seller}', [SellerProfileController::class, 'update']);

    Route::middleware('throttle:api-write')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::patch('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/products/{product}/favorite', [FavoriteController::class, 'store']);
    Route::delete('/products/{product}/favorite', [FavoriteController::class, 'destroy']);

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
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

    Route::post('/reports', [ReportController::class, 'store']);
});
