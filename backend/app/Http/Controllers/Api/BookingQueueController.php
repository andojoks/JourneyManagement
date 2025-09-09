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
     * Get queue status for a trip.
     * Trip creators can see all queue items, other users can only see their own queue position.
     */
    public function getQueueStatus(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
        ]);

        $user = auth()->user();
        $trip = Trip::findOrFail($request->trip_id);
        
        // Get full queue status
        $status = $this->queueService->getQueueStatus($trip);
        
        // If user is not the trip creator, filter to show only their queue items
        if ($trip->user_id !== $user->id) {
            $status['queue_items'] = collect($status['queue_items'])->filter(function ($item) use ($user) {
                return $item['user_id'] === $user->id;
            })->values()->toArray();
            
            // Update counts to reflect only user's items
            $status['total_requests'] = count($status['queue_items']);
            $status['pending_requests'] = collect($status['queue_items'])->where('status', 'pending')->count();
            $status['processing_requests'] = collect($status['queue_items'])->where('status', 'processing')->count();
            $status['completed_requests'] = collect($status['queue_items'])->where('status', 'completed')->count();
            $status['failed_requests'] = collect($status['queue_items'])->where('status', 'failed')->count();
        }

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
