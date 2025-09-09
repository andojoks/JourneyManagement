<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
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
     * Store a newly created booking.
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

        // Business rule: Check if enough seats are available
        if (!$trip->hasAvailableSeats($request->seats_reserved)) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough seats available.'
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

        try {
            DB::beginTransaction();

            // Create the booking
            $booking = $user->bookings()->create([
                'trip_id' => $trip->id,
                'seats_reserved' => $request->seats_reserved,
                'booking_time' => now(),
                'status' => 'confirmed',
            ]);

            // Update available seats
            $trip->decrement('available_seats', $request->seats_reserved);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking confirmed',
                'data' => [
                    'booking' => $booking->load(['trip.user'])
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking. Please try again.'
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

            $trip = $booking->trip;
            $originalSeats = $booking->seats_reserved;
            $newSeats = $request->input('seats_reserved', $originalSeats);
            $newStatus = $request->input('status', $booking->status);

            // If updating seats, check availability
            if ($newSeats !== $originalSeats) {
                // Calculate current total reserved seats (excluding this booking)
                $currentReservedSeats = $trip->confirmedBookings()
                    ->where('id', '!=', $booking->id)
                    ->sum('seats_reserved');

                // Check if new seats would exceed total capacity
                if (($currentReservedSeats + $newSeats) > $trip->total_seats) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not enough seats available. Requested seats would exceed trip capacity.'
                    ], 422);
                }

                // Update available seats based on the difference
                $seatDifference = $newSeats - $originalSeats;
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

            // Get the trip to update available seats
            $trip = $booking->trip;

            // Update booking status to cancelled
            $booking->update(['status' => 'cancelled']);

            // Restore available seats
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
}
