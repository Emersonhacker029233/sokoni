<?php

use App\Http\Controllers\Api\ReturnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Returns (docs/delivery/RETURNS.md)
|--------------------------------------------------------------------------
| Included from routes/api.php inside `delivery.enabled:returns`,
| `auth:sanctum` and `delivery.access:returns`: 404 for everyone while
| BOLT_ENABLED or DELIVERY_RETURNS_ENABLED is off, and for anyone outside
| BOLT_TEST_PHONES or the App Store review account.
*/

Route::get('/returns', [ReturnController::class, 'index']);
Route::get('/returns/{return}', [ReturnController::class, 'show'])->whereNumber('return');

Route::middleware('throttle:delivery-actions')->group(function () {
    Route::post('/orders/{order}/returns', [ReturnController::class, 'store']);
    Route::post('/returns/terms/accept', [ReturnController::class, 'acceptTerms']);
    Route::post('/returns/{return}/accept', [ReturnController::class, 'accept'])->whereNumber('return');
    Route::post('/returns/{return}/reject', [ReturnController::class, 'reject'])->whereNumber('return');
    Route::post('/returns/{return}/dispute', [ReturnController::class, 'dispute'])->whereNumber('return');
    Route::post('/returns/{return}/withdraw', [ReturnController::class, 'withdraw'])->whereNumber('return');
    Route::post('/returns/{return}/pay', [ReturnController::class, 'pay'])->whereNumber('return');
    Route::post('/returns/{return}/book', [ReturnController::class, 'book'])->whereNumber('return');
    Route::post('/returns/{return}/refunded', [ReturnController::class, 'refunded'])->whereNumber('return');
    Route::put('/shop/return-policy', [ReturnController::class, 'updatePolicy']);
});
