<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Models\Booking;
use App\Models\Trip;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom route model binding for Booking
        Route::bind('booking', function ($value) {
            $booking = Booking::find($value);
            
            if (!$booking) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404));
            }
            
            return $booking;
        });

        // Custom route model binding for Trip
        Route::bind('trip', function ($value) {
            $trip = Trip::find($value);
            
            if (!$trip) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Trip not found'
                ], 404));
            }
            
            return $trip;
        });
    }
}