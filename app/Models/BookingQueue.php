<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingQueue extends Model
{
    use HasFactory;

    protected $table = 'booking_queue';

    protected $fillable = [
        'user_id',
        'trip_id',
        'seats_requested',
        'priority_score',
        'status',
        'queued_at',
        'processed_at',
        'failure_reason',
    ];

    protected $casts = [
        'seats_requested' => 'integer',
        'priority_score' => 'integer',
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the user who made the booking request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trip being booked.
     */
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Scope to get pending bookings ordered by priority.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->orderBy('priority_score', 'desc')
            ->orderBy('queued_at', 'asc');
    }

    /**
     * Scope to get bookings for a specific trip.
     */
    public function scopeForTrip($query, $tripId)
    {
        return $query->where('trip_id', $tripId);
    }

    /**
     * Mark as processing.
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markAsCompleted()
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark as failed with reason.
     */
    public function markAsFailed(string $reason)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }
}
