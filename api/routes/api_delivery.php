<?php

use App\Http\Controllers\Api\Delivery\CustomerDeliveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery by Bolt (docs/delivery/BOLT.md)
|--------------------------------------------------------------------------
| Included from routes/api.php inside `delivery.enabled`, `auth:sanctum`
| and `delivery.access`: 404 for everyone while BOLT_ENABLED is off, and
| for anyone outside BOLT_TEST_PHONES or the App Store review account.
*/

Route::prefix('delivery')->group(function () {
    Route::get('/config', [CustomerDeliveryController::class, 'config']);
    Route::get('/quote', [CustomerDeliveryController::class, 'quote'])->middleware('throttle:delivery-quotes');
    Route::get('/orders/{order}', [CustomerDeliveryController::class, 'show']);
    Route::middleware('throttle:delivery-actions')->group(function () {
        Route::post('/orders/{order}/pay', [CustomerDeliveryController::class, 'pay']);
        Route::post('/orders/{order}/cancel', [CustomerDeliveryController::class, 'cancel']);
        Route::post('/orders/{order}/retry', [CustomerDeliveryController::class, 'retry']);
        Route::post('/orders/{order}/switch-to-pickup', [CustomerDeliveryController::class, 'switchToPickup']);
    });
});
