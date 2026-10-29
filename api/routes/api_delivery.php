<?php

use App\Http\Controllers\Api\Delivery\CustomerDeliveryController;
use App\Http\Controllers\Api\Delivery\DriverAvailabilityController;
use App\Http\Controllers\Api\Delivery\DriverJobController;
use App\Http\Controllers\Api\Delivery\DriverOfferController;
use App\Http\Controllers\Api\Delivery\DriverProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery + Sokoni Driver (docs/delivery/PLAN.md)
|--------------------------------------------------------------------------
| Included from routes/api.php inside `delivery.enabled` + `auth:sanctum`.
| With DELIVERY_ENABLED=false every route here returns 404.
| Drivers sign in through the existing /api/auth/* routes.
*/

Route::prefix('driver')->group(function () {
    // Onboarding: any signed-in account may start, no approval needed yet.
    Route::get('/me', [DriverProfileController::class, 'me']);
    Route::middleware('throttle:api-write')->group(function () {
        Route::post('/profile', [DriverProfileController::class, 'store']);
        Route::post('/profile/documents', [DriverProfileController::class, 'documents']);
        Route::put('/vehicle', [DriverProfileController::class, 'vehicle']);
        Route::post('/profile/submit', [DriverProfileController::class, 'submit']);
        Route::post('/devices', [DriverAvailabilityController::class, 'device']);
    });

    // Work: approved drivers only.
    Route::middleware('driver.approved')->group(function () {
        Route::patch('/availability', [DriverAvailabilityController::class, 'update'])->middleware('throttle:delivery-actions');
        Route::post('/location', [DriverAvailabilityController::class, 'location'])->middleware('throttle:driver-location');

        Route::get('/offers', [DriverOfferController::class, 'index']);
        Route::post('/offers/{job}/accept', [DriverOfferController::class, 'accept'])->middleware('throttle:delivery-actions');
        Route::post('/offers/{job}/decline', [DriverOfferController::class, 'decline'])->middleware('throttle:delivery-actions');

        Route::get('/jobs/active', [DriverJobController::class, 'active']);
        Route::get('/jobs', [DriverJobController::class, 'index']);
        Route::get('/jobs/{job}', [DriverJobController::class, 'show']);
        Route::post('/jobs/{job}/status', [DriverJobController::class, 'updateStatus'])->middleware('throttle:delivery-actions');
        Route::post('/jobs/{job}/call-attempts', [DriverJobController::class, 'callAttempt'])->middleware('throttle:delivery-actions');
        Route::post('/jobs/{job}/rating', [DriverJobController::class, 'rate'])->middleware('throttle:delivery-actions');

        Route::get('/earnings', [DriverJobController::class, 'earnings']);
    });
});

Route::prefix('delivery')->group(function () {
    Route::get('/quote', [CustomerDeliveryController::class, 'quote']);
    Route::get('/orders/{order}', [CustomerDeliveryController::class, 'show']);
    Route::middleware('throttle:delivery-actions')->group(function () {
        Route::post('/orders/{order}/cancel', [CustomerDeliveryController::class, 'cancel']);
        Route::post('/orders/{order}/retry', [CustomerDeliveryController::class, 'retry']);
        Route::post('/orders/{order}/switch-to-pickup', [CustomerDeliveryController::class, 'switchToPickup']);
        Route::post('/orders/{order}/rating', [CustomerDeliveryController::class, 'rate']);
        Route::post('/realtime-token', [CustomerDeliveryController::class, 'realtimeToken']);
    });
});
