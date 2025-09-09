<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TripController extends Controller
{
    /**
     * Display a listing of all trips with pagination and date filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $query = Trip::with(['user', 'bookings' => function($q) use ($user) {
            // For trip creators, show all bookings
            // For other users, show only their own booking
            $q->where(function($subQ) use ($user) {
                $subQ->whereHas('trip', function($tripQ) use ($user) {
                    $tripQ->where('user_id', $user->id);
                })->orWhere('user_id', $user->id);
            });
        }]);

        // Date filtering
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            if ($endDate->lt($startDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'End date must be after start date.'
                ], 422);
            }
            $query->where('start_time', '>=', $startDate)
                  ->where('end_time', '<=', $endDate);
        } elseif ($request->has('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('start_time', '>=', $startDate);
        } elseif ($request->has('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('end_time', '<=', $endDate);
        }

        // Pagination
        $perPage = $request->get('limit', 10);
        $trips = $query->orderBy('start_time', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $trips
        ]);
    }

    /**
     * Store a newly created trip
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'distance' => 'nullable|numeric|min:0',
            'trip_type' => 'nullable|string|in:personal,business',
            'status' => 'nullable|string|in:in-progress,completed,cancelled',
            'total_seats' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $totalSeats = $request->total_seats ?? 4;
        
        $trip = auth()->user()->trips()->create([
            'origin' => $request->origin,
            'destination' => $request->destination,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'distance' => $request->distance,
            'trip_type' => $request->trip_type ?? 'personal',
            'status' => $request->status ?? 'in-progress',
            'total_seats' => $totalSeats,
            'available_seats' => $totalSeats,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip created successfully',
            'data' => [
                'trip' => $trip
            ]
        ], 201);
    }

    /**
     * Display the specified trip
     */
    public function show(Trip $trip): JsonResponse
    {
        // Load only the trip creator information
        $trip->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'trip' => $trip
            ]
        ]);
    }

    /**
     * Update the specified trip
     */
    public function update(Request $request, Trip $trip): JsonResponse
    {
        // Ensure user can only update their own trips
        if ($trip->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own trips'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'origin' => 'sometimes|required|string|max:255',
            'destination' => 'sometimes|required|string|max:255',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'distance' => 'nullable|numeric|min:0',
            'trip_type' => 'nullable|string|in:personal,business',
            'status' => 'nullable|string|in:in-progress,completed,cancelled',
            'total_seats' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'origin', 'destination', 'start_time', 'end_time', 
            'distance', 'trip_type', 'status'
        ]);

        // Handle total_seats update
        if ($request->has('total_seats')) {
            $newTotalSeats = $request->total_seats;
            $currentReservedSeats = $trip->getTotalReservedSeats();
            
            // Ensure new total seats is not less than already reserved seats
            if ($newTotalSeats < $currentReservedSeats) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total seats cannot be less than already reserved seats (' . $currentReservedSeats . ')'
                ], 422);
            }
            
            $updateData['total_seats'] = $newTotalSeats;
            $updateData['available_seats'] = $newTotalSeats - $currentReservedSeats;
        }

        $trip->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Trip updated successfully',
            'data' => [
                'trip' => $trip->fresh()
            ]
        ]);
    }

    /**
     * Remove the specified trip
     */
    public function destroy(Trip $trip): JsonResponse
    {
        // Ensure user can only delete their own trips
        if ($trip->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own trips'
            ], 403);
        }

        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trip deleted successfully'
        ]);
    }

    /**
     * Get available trips for booking (trips with available seats)
     */
    public function available(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $query = Trip::with('user')
            ->withAvailableSeats()
            ->where('status', 'in-progress');

        // Date filtering
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            if ($endDate->lt($startDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'End date must be after start date.'
                ], 422);
            }
            $query->where('start_time', '>=', $startDate)
                  ->where('end_time', '<=', $endDate);
        } elseif ($request->has('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('start_time', '>=', $startDate);
        } elseif ($request->has('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('end_time', '<=', $endDate);
        }

        // Origin/Destination filtering
        if ($request->has('origin')) {
            $query->where('origin', 'like', '%' . $request->origin . '%');
        }
        if ($request->has('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }

        // Pagination
        $perPage = $request->get('limit', 10);
        $trips = $query->orderBy('start_time', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $trips
        ]);
    }
}
