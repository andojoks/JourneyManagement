<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RouteOptimizationService;
use App\Services\DynamicPricingService;
use App\Models\Waypoint;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RouteController extends Controller
{
    protected $routeService;
    protected $pricingService;

    public function __construct(
        RouteOptimizationService $routeService,
        DynamicPricingService $pricingService
    ) {
        $this->routeService = $routeService;
        $this->pricingService = $pricingService;
    }

    /**
     * Find optimal route between two waypoints.
     */
    public function findRoute(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|string',
            'to' => 'required|string',
        ]);

        try {
            $fromWaypoint = Waypoint::findByNameOrCity($request->from);
            $toWaypoint = Waypoint::findByNameOrCity($request->to);

            if (!$fromWaypoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Origin location not found'
                ], 404);
            }

            if (!$toWaypoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Destination location not found'
                ], 404);
            }

            $route = $this->routeService->findOptimalRoute($fromWaypoint, $toWaypoint);

            return response()->json([
                'success' => true,
                'data' => [
                    'route' => $route,
                    'from' => $fromWaypoint,
                    'to' => $toWaypoint,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Search for waypoints.
     */
    public function searchWaypoints(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $waypoints = $this->routeService->findWaypoints($request->query);

        return response()->json([
            'success' => true,
            'data' => [
                'waypoints' => $waypoints
            ]
        ]);
    }

    /**
     * Calculate dynamic pricing for a trip.
     */
    public function calculatePricing(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
        ]);

        $trip = Trip::findOrFail($request->trip_id);
        $pricing = $this->pricingService->calculateSurgePricing($trip);

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'pricing' => $pricing,
            ]
        ]);
    }

    /**
     * Update pricing for multiple trips.
     */
    public function updateBulkPricing(Request $request): JsonResponse
    {
        $request->validate([
            'trip_ids' => 'required|array',
            'trip_ids.*' => 'integer|exists:trips,id',
        ]);

        $pricing = $this->pricingService->getBulkPricing($request->trip_ids);

        return response()->json([
            'success' => true,
            'data' => [
                'pricing' => $pricing,
            ]
        ]);
    }

    /**
     * Get route between cities.
     */
    public function getRouteBetweenCities(Request $request): JsonResponse
    {
        $request->validate([
            'from_city' => 'required|string',
            'to_city' => 'required|string',
        ]);

        try {
            $route = $this->routeService->getRouteBetweenCities(
                $request->from_city,
                $request->to_city
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'route' => $route,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
