<?php

namespace App\Services;

use App\Models\Waypoint;
use App\Models\RouteSegment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RouteOptimizationService
{
    /**
     * Find the optimal route between two waypoints using Dijkstra's algorithm.
     */
    public function findOptimalRoute(Waypoint $start, Waypoint $end): array
    {
        $cacheKey = "route:{$start->id}:{$end->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($start, $end) { // 1 hour cache
            $distances = [];
            $previous = [];
            $unvisited = collect();
            $visited = collect();

            // Initialize distances
            $allWaypoints = Waypoint::all();
            foreach ($allWaypoints as $waypoint) {
                $distances[$waypoint->id] = $waypoint->id === $start->id ? 0 : PHP_FLOAT_MAX;
                $previous[$waypoint->id] = null;
                $unvisited->push($waypoint);
            }

            while ($unvisited->isNotEmpty()) {
                // Find the unvisited waypoint with the smallest distance
                $current = $unvisited->sortBy(function ($waypoint) use ($distances) {
                    return $distances[$waypoint->id];
                })->first();

                if ($current->id === $end->id) {
                    break; // Reached destination
                }

                $unvisited = $unvisited->reject(function ($waypoint) use ($current) {
                    return $waypoint->id === $current->id;
                });

                $visited->push($current);

                // Check all neighbors of current waypoint
                $neighbors = $this->getNeighbors($current);
                
                foreach ($neighbors as $neighbor) {
                    if ($visited->contains('id', $neighbor->id)) {
                        continue;
                    }

                    $segment = RouteSegment::where('from_waypoint_id', $current->id)
                        ->where('to_waypoint_id', $neighbor->id)
                        ->active()
                        ->first();

                    if (!$segment) {
                        continue;
                    }

                    $alt = $distances[$current->id] + $segment->distance;

                    if ($alt < $distances[$neighbor->id]) {
                        $distances[$neighbor->id] = $alt;
                        $previous[$neighbor->id] = $current->id;
                    }
                }
            }

            // Reconstruct the path
            $path = $this->reconstructPath($previous, $start->id, $end->id);
            
            if (empty($path)) {
                throw new \Exception('No route found between the specified waypoints');
            }

            return [
                'waypoints' => $path,
                'total_distance' => $distances[$end->id],
                'total_time' => $this->calculateTotalTime($path),
                'total_base_price' => $this->calculateTotalBasePrice($path),
            ];
        });
    }

    /**
     * Get neighboring waypoints for a given waypoint.
     */
    private function getNeighbors(Waypoint $waypoint): Collection
    {
        $segmentIds = RouteSegment::where('from_waypoint_id', $waypoint->id)
            ->active()
            ->pluck('to_waypoint_id');

        return Waypoint::whereIn('id', $segmentIds)->get();
    }

    /**
     * Reconstruct the path from start to end.
     */
    private function reconstructPath(array $previous, int $startId, int $endId): array
    {
        $path = [];
        $current = $endId;

        while ($current !== null) {
            array_unshift($path, $current);
            $current = $previous[$current];
        }

        // Verify the path starts from the correct waypoint
        if (empty($path) || $path[0] !== $startId) {
            return [];
        }

        return Waypoint::whereIn('id', $path)->orderByRaw('FIELD(id, ' . implode(',', $path) . ')')->get()->toArray();
    }

    /**
     * Calculate total estimated time for the route.
     */
    private function calculateTotalTime(array $waypoints): int
    {
        $totalTime = 0;
        
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $segment = RouteSegment::where('from_waypoint_id', $waypoints[$i]['id'])
                ->where('to_waypoint_id', $waypoints[$i + 1]['id'])
                ->active()
                ->first();

            if ($segment) {
                $totalTime += $segment->estimated_time;
            }
        }

        return $totalTime;
    }

    /**
     * Calculate total base price for the route.
     */
    private function calculateTotalBasePrice(array $waypoints): float
    {
        $totalPrice = 0;
        
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $segment = RouteSegment::where('from_waypoint_id', $waypoints[$i]['id'])
                ->where('to_waypoint_id', $waypoints[$i + 1]['id'])
                ->active()
                ->first();

            if ($segment) {
                $totalPrice += $segment->base_price;
            }
        }

        return $totalPrice;
    }

    /**
     * Find waypoints by name or city with caching.
     */
    public function findWaypoints(string $query): Collection
    {
        $cacheKey = "waypoints_search:" . md5($query);
        
        return Cache::remember($cacheKey, 1800, function () use ($query) { // 30 minutes cache
            return Waypoint::where('name', 'like', "%{$query}%")
                ->orWhere('city', 'like', "%{$query}%")
                ->get();
        });
    }

    /**
     * Get route between two cities by name with caching.
     */
    public function getRouteBetweenCities(string $fromCity, string $toCity): array
    {
        $cacheKey = "route_cities:" . md5($fromCity . ':' . $toCity);
        
        return Cache::remember($cacheKey, 3600, function () use ($fromCity, $toCity) { // 1 hour cache
            $fromWaypoint = Waypoint::where('city', 'like', "%{$fromCity}%")->first();
            $toWaypoint = Waypoint::where('city', 'like', "%{$toCity}%")->first();

            if (!$fromWaypoint || !$toWaypoint) {
                throw new \Exception('One or both cities not found');
            }

            return $this->findOptimalRoute($fromWaypoint, $toWaypoint);
        });
    }

    /**
     * Invalidate route caches for a waypoint
     */
    public function invalidateRouteCaches(Waypoint $waypoint): void
    {
        try {
            $redis = Cache::getRedis();
            $keys = $redis->keys("route:*{$waypoint->id}*");
            if (!empty($keys)) {
                $redis->del($keys);
            }
            
            // Also invalidate city-based route caches
            $cityKeys = $redis->keys("route_cities:*{$waypoint->city}*");
            if (!empty($cityKeys)) {
                $redis->del($cityKeys);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to invalidate route caches', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cached route if available
     */
    public function getCachedRoute(int $startWaypointId, int $endWaypointId): ?array
    {
        $cacheKey = "route:{$startWaypointId}:{$endWaypointId}";
        return Cache::get($cacheKey);
    }
}
