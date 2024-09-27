<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::name('api.')
    ->prefix('api/')
    ->namespace('Api')
    ->group(static function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::prefix('products')->group(function () {
                Route::post('{product}/purchase', [ProductController::class, 'purchase'])->name('product.purchase');
                Route::post('{product}/rent', [ProductController::class, 'rent'])->name('product.rent');
                Route::get('{product}/status', [ProductController::class, 'checkStatus'])->name('product.status');
            });

            Route::prefix('transactions')->group(function () {
                Route::post('{transaction}/extend', [ProductController::class, 'extendRent'])->name('transaction.rent.extend');
            });
        });

    });