<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingQueueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    protected $bookingQueueService;

    public function __construct(BookingQueueService $bookingQueueService)
    {
        $this->bookingQueueService = $bookingQueueService;
    }
    /**
     * Display a listing of the user's bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $query = $user->bookings()->with(['trip.user']);

        // Filter by trip_id if provided
        if ($request->has('trip_id')) {
            $query->where('trip_id', $request->trip_id);
        }

        // Pagination
        $perPage = $request->get('limit', 10);
        $bookings = $query->orderBy('booking_time', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings
        ]);
    }

    /**
     * Store a newly created booking request in the queue system.
     * All booking requests are queued and processed automatically by background jobs.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|integer|exists:trips,id',
            'seats_reserved' => 'required|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $trip = Trip::findOrFail($request->trip_id);

        // Business rule: Cannot book your own trip
        if ($trip->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot book your own trip.'
            ], 422);
        }

        // Business rule: Check if user already has a booking for this trip
        $existingBooking = Booking::where('user_id', $user->id)
            ->where('trip_id', $trip->id)
            ->first();

        if ($existingBooking) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a booking for this trip.'
            ], 422);
        }

        // Check if user already has a pending queue request for this trip
        $existingQueueItem = \App\Models\BookingQueue::where('user_id', $user->id)
            ->where('trip_id', $trip->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingQueueItem) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending booking request for this trip.'
            ], 422);
        }

        try {
            // Add booking request to queue
            $queueItem = $this->bookingQueueService->addToQueue(
                $user, 
                $trip, 
                $request->seats_reserved
            );

            // Get queue position
            $queuePosition = $this->getQueuePosition($queueItem);

            return response()->json([
                'success' => true,
                'message' => 'Booking request added to queue',
                'data' => [
                    'queue_info' => [
                        'queue_id' => $queueItem->id,
                        'priority_score' => $queueItem->priority_score,
                        'estimated_position' => $queuePosition,
                        'status' => $queueItem->status,
                        'trip_info' => [
                            'trip_id' => $trip->id,
                            'origin' => $trip->origin,
                            'destination' => $trip->destination,
                            'start_time' => $trip->start_time,
                            'available_seats' => $trip->available_seats,
                            'total_seats' => $trip->total_seats
                        ]
                    ]
                ]
            ], 202); // 202 Accepted - request queued

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add booking request to queue. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user can access this booking
        // User can see booking if they made it OR if they created the trip
        if ($booking->user_id !== $user->id && $booking->trip->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only access bookings you made or trips you created'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'booking' => $booking->load(['trip.user'])
            ]
        ]);
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user can update this booking
        // User can update booking if they made it OR if they created the trip
        if ($booking->user_id !== $user->id && $booking->trip->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update bookings you made or trips you created'
            ], 403);
        }

        $request->validate([
            'seats_reserved' => 'nullable|integer|min:1|max:10',
            'status' => 'nullable|string|in:confirmed,cancelled'
        ]);

        try {
            DB::beginTransaction();

            // Lock the trip row to prevent race conditions
            $trip = Trip::where('id', $booking->trip_id)->lockForUpdate()->first();
            
            if (!$trip) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Trip not found.'
                ], 404);
            }

            $originalSeats = $booking->seats_reserved;
            $newSeats = $request->input('seats_reserved', $originalSeats);
            $newStatus = $request->input('status', $booking->status);

            // If updating seats, check availability with proper locking
            if ($newSeats !== $originalSeats) {
                // Check if new seats would exceed total capacity (using locked trip data)
                if ($newSeats > $trip->total_seats) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Requested seats exceed trip total capacity.'
                    ], 422);
                }

                // Check if there are enough available seats for the increase
                $seatDifference = $newSeats - $originalSeats;
                if ($seatDifference > 0 && $trip->available_seats < $seatDifference) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Trip is full. Not enough seats available for this update.'
                    ], 422);
                }

                // Update available seats based on the difference
                $trip->increment('available_seats', -$seatDifference);
            }

            // If changing status from confirmed to cancelled, restore seats
            if ($booking->status === 'confirmed' && $newStatus === 'cancelled') {
                $trip->increment('available_seats', $booking->seats_reserved);
            }
            // If changing status from cancelled to confirmed, reserve seats
            elseif ($booking->status === 'cancelled' && $newStatus === 'confirmed') {
                // Check if there are enough seats available
                if ($trip->available_seats < $newSeats) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not enough seats available for this booking.'
                    ], 422);
                }
                $trip->decrement('available_seats', $newSeats);
            }

            // Update the booking
            $booking->update([
                'seats_reserved' => $newSeats,
                'status' => $newStatus
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking updated successfully',
                'data' => [
                    'booking' => $booking->load(['trip.user'])
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified booking (cancel booking).
     */
    public function destroy(Booking $booking): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user can cancel this booking
        // User can cancel booking if they made it OR if they created the trip
        if ($booking->user_id !== $user->id && $booking->trip->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only cancel bookings you made or trips you created'
            ], 403);
        }

        // Check if booking is already cancelled
        if ($booking->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Lock the trip row to prevent race conditions
            $trip = Trip::where('id', $booking->trip_id)->lockForUpdate()->first();
            
            if (!$trip) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Trip not found.'
                ], 404);
            }

            // Update booking status to cancelled
            $booking->update(['status' => 'cancelled']);

            // Restore available seats (atomic operation)
            $trip->increment('available_seats', $booking->seats_reserved);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking. Please try again.'
            ], 500);
        }
    }

    /**
     * Get the position of a queue item in the queue.
     */
    private function getQueuePosition($queueItem): int
    {
        $position = \App\Models\BookingQueue::where('trip_id', $queueItem->trip_id)
            ->where('status', 'pending')
            ->where(function ($query) use ($queueItem) {
                $query->where('priority_score', '>', $queueItem->priority_score)
                    ->orWhere(function ($q) use ($queueItem) {
                        $q->where('priority_score', '=', $queueItem->priority_score)
                          ->where('queued_at', '<', $queueItem->queued_at);
                    });
            })
            ->count();

        return $position + 1; // Position is 1-based
    }
}
