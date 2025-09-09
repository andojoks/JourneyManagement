<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingQueueService;
use App\Models\Trip;
use App\Models\BookingQueue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingQueueController extends Controller
{
    protected $queueService;

    public function __construct(BookingQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Add a booking request to the queue.
     */
    public function addToQueue(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
            'seats_requested' => 'required|integer|min:1|max:10',
        ]);

        $user = auth()->user();
        $trip = Trip::findOrFail($request->trip_id);

        // Business rule: Cannot book your own trip
        if ($trip->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot book your own trip.'
            ], 422);
        }

        // Check if user already has a booking for this trip
        $existingBooking = \App\Models\Booking::where('user_id', $user->id)
            ->where('trip_id', $trip->id)
            ->first();

        if ($existingBooking) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a booking for this trip.'
            ], 422);
        }

        try {
            $queueItem = $this->queueService->addToQueue($user, $trip, $request->seats_requested);

            return response()->json([
                'success' => true,
                'message' => 'Booking request added to queue',
                'data' => [
                    'queue_id' => $queueItem->id,
                    'priority_score' => $queueItem->priority_score,
                    'estimated_position' => $this->getEstimatedPosition($queueItem),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add booking to queue'
            ], 500);
        }
    }

    /**
     * Process booking queue for a specific trip.
     */
    public function processQueue(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
        ]);

        $trip = Trip::findOrFail($request->trip_id);

        // Only trip creator can process the queue
        if ($trip->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Only trip creator can process the booking queue'
            ], 403);
        }

        try {
            $results = $this->queueService->processQueueForTrip($trip);

            return response()->json([
                'success' => true,
                'message' => 'Queue processed successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process queue'
            ], 500);
        }
    }

    /**
     * Get queue status for a trip.
     */
    public function getQueueStatus(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
        ]);

        $trip = Trip::findOrFail($request->trip_id);
        $status = $this->queueService->getQueueStatus($trip);

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Get user's queue positions.
     */
    public function getUserQueuePositions(): JsonResponse
    {
        $user = auth()->user();
        
        $queueItems = BookingQueue::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('trip')
            ->orderBy('queued_at', 'asc')
            ->get();

        $positions = $queueItems->map(function ($item) {
            return [
                'queue_id' => $item->id,
                'trip_id' => $item->trip_id,
                'trip_origin' => $item->trip->origin,
                'trip_destination' => $item->trip->destination,
                'seats_requested' => $item->seats_requested,
                'priority_score' => $item->priority_score,
                'queued_at' => $item->queued_at,
                'estimated_position' => $this->getEstimatedPosition($item),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'queue_positions' => $positions
            ]
        ]);
    }

    /**
     * Cancel a queued booking request.
     */
    public function cancelQueueItem(Request $request): JsonResponse
    {
        $request->validate([
            'queue_id' => 'required|integer|exists:booking_queue,id',
        ]);

        $user = auth()->user();
        $queueItem = BookingQueue::where('id', $request->queue_id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$queueItem) {
            return response()->json([
                'success' => false,
                'message' => 'Queue item not found or cannot be cancelled'
            ], 404);
        }

        $queueItem->markAsFailed('Cancelled by user');

        return response()->json([
            'success' => true,
            'message' => 'Queue item cancelled successfully'
        ]);
    }

    /**
     * Get estimated position in queue.
     */
    private function getEstimatedPosition(BookingQueue $queueItem): int
    {
        $higherPriorityCount = BookingQueue::where('trip_id', $queueItem->trip_id)
            ->where('status', 'pending')
            ->where(function ($query) use ($queueItem) {
                $query->where('priority_score', '>', $queueItem->priority_score)
                    ->orWhere(function ($subQuery) use ($queueItem) {
                        $subQuery->where('priority_score', '=', $queueItem->priority_score)
                            ->where('queued_at', '<', $queueItem->queued_at);
                    });
            })
            ->count();

        return $higherPriorityCount + 1;
    }
}
