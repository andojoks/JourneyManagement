<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\BookingQueueController;

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

Route::group(['prefix' => 'v1'], function () {
    // Public routes (no authentication required)
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // Protected routes (authentication required)
    Route::middleware('jwt.auth')->group(function () {
        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // Trip routes
        Route::apiResource('trips', TripController::class);
        Route::get('trips/available', [TripController::class, 'available']);

        // Booking routes
        Route::apiResource('bookings', BookingController::class);

        // Route optimization routes
        Route::prefix('routes')->group(function () {
            Route::get('search', [RouteController::class, 'searchWaypoints']);
            Route::get('find', [RouteController::class, 'findRoute']);
            Route::get('cities', [RouteController::class, 'getRouteBetweenCities']);
            Route::post('pricing', [RouteController::class, 'calculatePricing']);
            Route::post('pricing/bulk', [RouteController::class, 'updateBulkPricing']);
        });

        // Booking queue routes
        Route::prefix('queue')->group(function () {
            Route::post('add', [BookingQueueController::class, 'addToQueue']);
            Route::post('process', [BookingQueueController::class, 'processQueue']);
            Route::get('status', [BookingQueueController::class, 'getQueueStatus']);
            Route::get('positions', [BookingQueueController::class, 'getUserQueuePositions']);
            Route::delete('cancel', [BookingQueueController::class, 'cancelQueueItem']);
        });
    });
});
