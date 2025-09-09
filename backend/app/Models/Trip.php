<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'origin',
        'destination',
        'start_time',
        'end_time',
        'status',
        'distance',
        'trip_type',
        'total_seats',
        'available_seats',
        'base_price',
        'surge_multiplier',
        'final_price',
        'route_waypoints',
        'priority_score',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'distance' => 'float',
        'total_seats' => 'integer',
        'available_seats' => 'integer',
        'base_price' => 'decimal:2',
        'surge_multiplier' => 'decimal:2',
        'final_price' => 'decimal:2',
        'route_waypoints' => 'array',
        'priority_score' => 'integer',
    ];

    /**
     * Get the user that owns the trip.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bookings for the trip.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the confirmed bookings for the trip.
     */
    public function confirmedBookings()
    {
        return $this->hasMany(Booking::class)->confirmed();
    }

    /**
     * Get the booking queue items for the trip.
     */
    public function bookingQueues()
    {
        return $this->hasMany(\App\Models\BookingQueue::class);
    }

    /**
     * Scope a query to only include trips for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter trips by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include trips with available seats.
     */
    public function scopeWithAvailableSeats($query)
    {
        return $query->where('available_seats', '>', 0);
    }

    /**
     * Check if the trip has enough available seats.
     */
    public function hasAvailableSeats($seatsRequested = 1)
    {
        return $this->available_seats >= $seatsRequested;
    }

    /**
     * Get the total number of seats reserved for this trip.
     */
    public function getTotalReservedSeats()
    {
        return $this->confirmedBookings()->sum('seats_reserved');
    }
}
