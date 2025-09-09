<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\CacheService;

class Booking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'trip_id',
        'seats_reserved',
        'booking_time',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'booking_time' => 'datetime',
        'seats_reserved' => 'integer',
    ];

    /**
     * Get the user that owns the booking.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trip that is booked.
     */
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Scope a query to only include bookings for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include confirmed bookings.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include cancelled bookings.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Model event handlers for cache invalidation
     */
    protected static function booted()
    {
        static::created(function ($booking) {
            app(CacheService::class)->invalidateBookingCaches($booking);
        });

        static::updated(function ($booking) {
            app(CacheService::class)->invalidateBookingCaches($booking);
        });

        static::deleted(function ($booking) {
            app(CacheService::class)->invalidateBookingCaches($booking);
        });
    }
}
