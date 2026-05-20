<?php

use App\Http\Controllers\Api\V1\ApiAuthController;
use App\Http\Controllers\Api\V1\ApiOrderController;
use App\Http\Controllers\Api\V1\ApiWalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [ApiAuthController::class, 'login']);

    Route::middleware('api.token')->group(function (): void {
        Route::get('wallet', [ApiWalletController::class, 'show']);
        Route::post('orders', [ApiOrderController::class, 'store']);
        Route::post('orders/{order}/cancel', [ApiOrderController::class, 'cancel']);
    });
});
