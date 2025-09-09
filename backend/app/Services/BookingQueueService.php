<?php

namespace App\Services;

use App\Models\BookingQueue;
use App\Models\Trip;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingQueueService
{
    /**
     * Add a booking request to the queue.
     */
    public function addToQueue(User $user, Trip $trip, int $seatsRequested): BookingQueue
    {
        $priorityScore = $this->calculatePriorityScore($user, $trip, $seatsRequested);

        return BookingQueue::create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'seats_requested' => $seatsRequested,
            'priority_score' => $priorityScore,
            'status' => 'pending',
            'queued_at' => now(),
        ]);
    }

    /**
     * Process the booking queue for a specific trip.
     */
    public function processQueueForTrip(Trip $trip): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Get pending bookings for this trip, ordered by priority
        $pendingBookings = BookingQueue::forTrip($trip->id)
            ->pending()
            ->get();

        foreach ($pendingBookings as $queueItem) {
            try {
                $results['processed']++;
                
                $queueItem->markAsProcessing();

                // Lock the trip to prevent race conditions
                $lockedTrip = Trip::where('id', $trip->id)->lockForUpdate()->first();
                
                if (!$lockedTrip) {
                    $queueItem->markAsFailed('Trip not found');
                    $results['failed']++;
                    continue;
                }

                // Check if seats are still available
                if ($lockedTrip->available_seats < $queueItem->seats_requested) {
                    $queueItem->markAsFailed('Not enough seats available');
                    $results['failed']++;
                    continue;
                }

                // Check if user already has a booking for this trip
                $existingBooking = Booking::where('user_id', $queueItem->user_id)
                    ->where('trip_id', $trip->id)
                    ->first();

                if ($existingBooking) {
                    $queueItem->markAsFailed('User already has a booking for this trip');
                    $results['failed']++;
                    continue;
                }

                // Create the booking
                $booking = Booking::create([
                    'user_id' => $queueItem->user_id,
                    'trip_id' => $trip->id,
                    'seats_reserved' => $queueItem->seats_requested,
                    'booking_time' => now(),
                    'status' => 'confirmed',
                ]);

                // Update available seats
                $lockedTrip->decrement('available_seats', $queueItem->seats_requested);

                $queueItem->markAsCompleted();
                $results['successful']++;

            } catch (\Exception $e) {
                $queueItem->markAsFailed('Processing error: ' . $e->getMessage());
                $results['failed']++;
                $results['errors'][] = [
                    'queue_id' => $queueItem->id,
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Booking queue processing error', [
                    'queue_id' => $queueItem->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Process all pending bookings in the queue.
     */
    public function processAllQueues(): array
    {
        $allResults = [
            'total_processed' => 0,
            'total_successful' => 0,
            'total_failed' => 0,
            'trip_results' => [],
        ];

        // Get all unique trip IDs with pending bookings
        $tripIds = BookingQueue::pending()
            ->distinct()
            ->pluck('trip_id');

        foreach ($tripIds as $tripId) {
            $trip = Trip::find($tripId);
            if ($trip) {
                $results = $this->processQueueForTrip($trip);
                $allResults['trip_results'][$tripId] = $results;
                $allResults['total_processed'] += $results['processed'];
                $allResults['total_successful'] += $results['successful'];
                $allResults['total_failed'] += $results['failed'];
            }
        }

        return $allResults;
    }

    /**
     * Calculate priority score for a booking request.
     */
    private function calculatePriorityScore(User $user, Trip $trip, int $seatsRequested): int
    {
        $score = 0;

        // Factor 1: User loyalty (based on previous bookings)
        $userBookings = Booking::where('user_id', $user->id)->count();
        $score += min($userBookings * 2, 50); // Max 50 points for loyalty

        // Factor 2: Early booking (bookings made well in advance)
        $tripStartTime = \Carbon\Carbon::parse($trip->start_time);
        $hoursUntilTrip = now()->diffInHours($tripStartTime, false);
        
        if ($hoursUntilTrip > 24) {
            $score += 30; // 30 points for booking more than 24 hours in advance
        } elseif ($hoursUntilTrip > 12) {
            $score += 20; // 20 points for booking more than 12 hours in advance
        } elseif ($hoursUntilTrip > 6) {
            $score += 10; // 10 points for booking more than 6 hours in advance
        }

        // Factor 3: Trip creator priority (if user created the trip)
        if ($trip->user_id === $user->id) {
            $score += 100; // High priority for trip creators
        }

        // Factor 4: Seat quantity (slight preference for fewer seats)
        if ($seatsRequested === 1) {
            $score += 5; // Small bonus for single seat bookings
        }

        return $score;
    }

    /**
     * Get queue status for a specific trip.
     */
    public function getQueueStatus(Trip $trip): array
    {
        $queueItems = BookingQueue::forTrip($trip->id)
            ->orderBy('priority_score', 'desc')
            ->orderBy('queued_at', 'asc')
            ->get();

        return [
            'trip_id' => $trip->id,
            'total_requests' => $queueItems->count(),
            'pending_requests' => $queueItems->where('status', 'pending')->count(),
            'processing_requests' => $queueItems->where('status', 'processing')->count(),
            'completed_requests' => $queueItems->where('status', 'completed')->count(),
            'failed_requests' => $queueItems->where('status', 'failed')->count(),
            'queue_items' => $queueItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'seats_requested' => $item->seats_requested,
                    'priority_score' => $item->priority_score,
                    'status' => $item->status,
                    'queued_at' => $item->queued_at,
                    'processed_at' => $item->processed_at,
                    'failure_reason' => $item->failure_reason,
                ];
            }),
        ];
    }

    /**
     * Clean up old queue items.
     */
    public function cleanupOldQueueItems(int $daysOld = 7): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        return BookingQueue::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['completed', 'failed'])
            ->delete();
    }
}
