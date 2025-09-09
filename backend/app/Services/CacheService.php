<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\Trip;
use App\Models\Booking;
use App\Models\Waypoint;

class CacheService
{
    /**
     * Cache TTL constants (in seconds)
     */
    const TTL_TRIP_LISTINGS = 300;        // 5 minutes
    const TTL_AVAILABLE_TRIPS = 180;      // 3 minutes
    const TTL_PRICING_DATA = 120;         // 2 minutes
    const TTL_ROUTE_CALCULATIONS = 3600;  // 1 hour
    const TTL_WAYPOINT_SEARCH = 1800;     // 30 minutes
    const TTL_USER_DATA = 600;            // 10 minutes

    /**
     * Invalidate trip-related caches
     */
    public function invalidateTripCaches(Trip $trip): void
    {
        // Invalidate specific trip caches
        Cache::forget("trip:{$trip->id}");
        Cache::forget("trip:{$trip->id}:bookings");
        Cache::forget("trip:{$trip->id}:pricing");
        
        // Invalidate pattern-based caches
        $this->invalidatePatternCaches('trips:*');
        $this->invalidatePatternCaches('available_trips:*');
        $this->invalidatePatternCaches('user_trips:*');
        $this->invalidatePatternCaches('pricing:*');
    }

    /**
     * Invalidate booking-related caches
     */
    public function invalidateBookingCaches(Booking $booking): void
    {
        $this->invalidateTripCaches($booking->trip);
        $this->invalidatePatternCaches('user_bookings:*');
        $this->invalidatePatternCaches('booking_queue:*');
    }

    /**
     * Invalidate waypoint-related caches
     */
    public function invalidateWaypointCaches(Waypoint $waypoint): void
    {
        Cache::forget("waypoint:{$waypoint->id}");
        $this->invalidatePatternCaches('waypoints:*');
        $this->invalidatePatternCaches('route:*');
    }

    /**
     * Invalidate caches matching a pattern
     */
    public function invalidatePatternCaches(string $pattern): void
    {
        try {
            $redis = Redis::connection('cache');
            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to invalidate cache pattern: {$pattern}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cached trips with intelligent key generation
     */
    public function getCachedTrips(array $filters = [], int $userId = null): mixed
    {
        $cacheKey = $this->generateCacheKey('trips', $filters, $userId);
        
        return Cache::remember($cacheKey, self::TTL_TRIP_LISTINGS, function () use ($filters, $userId) {
            $query = Trip::with(['user', 'bookings']);
            
            if ($userId) {
                $query->where('user_id', $userId);
            }
            
            if (isset($filters['start_date'])) {
                $query->where('start_time', '>=', $filters['start_date']);
            }
            
            if (isset($filters['end_date'])) {
                $query->where('end_time', '<=', $filters['end_date']);
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            return $query->orderBy('start_time', 'desc')->get();
        });
    }

    /**
     * Get cached available trips
     */
    public function getCachedAvailableTrips(array $filters = []): mixed
    {
        $cacheKey = $this->generateCacheKey('available_trips', $filters);
        
        return Cache::remember($cacheKey, self::TTL_AVAILABLE_TRIPS, function () use ($filters) {
            $query = Trip::with('user')
                ->withAvailableSeats()
                ->where('status', 'in-progress');
                
            if (isset($filters['origin'])) {
                $query->where('origin', 'like', '%' . $filters['origin'] . '%');
            }
            
            if (isset($filters['destination'])) {
                $query->where('destination', 'like', '%' . $filters['destination'] . '%');
            }
            
            if (isset($filters['start_date'])) {
                $query->where('start_time', '>=', $filters['start_date']);
            }
            
            if (isset($filters['end_date'])) {
                $query->where('end_time', '<=', $filters['end_date']);
            }
            
            return $query->orderBy('start_time', 'asc')->get();
        });
    }

    /**
     * Get cached pricing data
     */
    public function getCachedPricing(int $tripId): mixed
    {
        $cacheKey = "pricing:trip:{$tripId}";
        
        return Cache::remember($cacheKey, self::TTL_PRICING_DATA, function () use ($tripId) {
            $trip = Trip::find($tripId);
            if (!$trip) {
                return null;
            }
            
            return app(\App\Services\DynamicPricingService::class)->calculateSurgePricing($trip);
        });
    }

    /**
     * Get cached route calculation
     */
    public function getCachedRoute(int $startWaypointId, int $endWaypointId): mixed
    {
        $cacheKey = "route:{$startWaypointId}:{$endWaypointId}";
        
        return Cache::remember($cacheKey, self::TTL_ROUTE_CALCULATIONS, function () use ($startWaypointId, $endWaypointId) {
            $start = Waypoint::find($startWaypointId);
            $end = Waypoint::find($endWaypointId);
            
            if (!$start || !$end) {
                return null;
            }
            
            return app(\App\Services\RouteOptimizationService::class)->findOptimalRoute($start, $end);
        });
    }

    /**
     * Get cached waypoint search results
     */
    public function getCachedWaypointSearch(string $query): mixed
    {
        $cacheKey = "waypoints_search:" . md5($query);
        
        return Cache::remember($cacheKey, self::TTL_WAYPOINT_SEARCH, function () use ($query) {
            return Waypoint::where('name', 'like', "%{$query}%")
                ->orWhere('city', 'like', "%{$query}%")
                ->get();
        });
    }

    /**
     * Get cached user bookings
     */
    public function getCachedUserBookings(int $userId, array $filters = []): mixed
    {
        $cacheKey = $this->generateCacheKey('user_bookings', $filters, $userId);
        
        return Cache::remember($cacheKey, self::TTL_USER_DATA, function () use ($userId, $filters) {
            $query = Booking::with(['trip.user'])->where('user_id', $userId);
            
            if (isset($filters['trip_id'])) {
                $query->where('trip_id', $filters['trip_id']);
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            return $query->orderBy('booking_time', 'desc')->get();
        });
    }

    /**
     * Generate consistent cache keys
     */
    private function generateCacheKey(string $prefix, array $filters = [], int $userId = null): string
    {
        $keyParts = [$prefix];
        
        if ($userId) {
            $keyParts[] = "user:{$userId}";
        }
        
        if (!empty($filters)) {
            $keyParts[] = md5(serialize($filters));
        }
        
        return implode(':', $keyParts);
    }

    /**
     * Warm up frequently accessed caches
     */
    public function warmUpCaches(): void
    {
        try {
            // Cache popular routes
            $this->warmUpPopularRoutes();
            
            // Cache recent trips
            $this->warmUpRecentTrips();
            
            // Cache available trips
            $this->warmUpAvailableTrips();
            
            \Log::info('Cache warming completed successfully');
        } catch (\Exception $e) {
            \Log::error('Cache warming failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Warm up popular routes
     */
    private function warmUpPopularRoutes(): void
    {
        $popularRoutes = [
            ['from' => 'New York', 'to' => 'Boston'],
            ['from' => 'Los Angeles', 'to' => 'San Francisco'],
            ['from' => 'Chicago', 'to' => 'Detroit'],
        ];

        foreach ($popularRoutes as $route) {
            try {
                $fromWaypoint = Waypoint::where('city', 'like', "%{$route['from']}%")->first();
                $toWaypoint = Waypoint::where('city', 'like', "%{$route['to']}%")->first();
                
                if ($fromWaypoint && $toWaypoint) {
                    $this->getCachedRoute($fromWaypoint->id, $toWaypoint->id);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to warm up route: {$route['from']} -> {$route['to']}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Warm up recent trips
     */
    private function warmUpRecentTrips(): void
    {
        try {
            $recentTrips = Trip::with('user')
                ->where('start_time', '>=', now()->subDays(7))
                ->orderBy('start_time', 'desc')
                ->limit(50)
                ->get();
                
            foreach ($recentTrips as $trip) {
                Cache::put("trip:{$trip->id}", $trip, self::TTL_TRIP_LISTINGS);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to warm up recent trips', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Warm up available trips
     */
    private function warmUpAvailableTrips(): void
    {
        try {
            $this->getCachedAvailableTrips();
        } catch (\Exception $e) {
            \Log::warning('Failed to warm up available trips', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clear all application caches
     */
    public function clearAllCaches(): void
    {
        try {
            Cache::flush();
            \Log::info('All caches cleared successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to clear caches', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $redis = Redis::connection('cache');
            $info = $redis->info();
            
            return [
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 'N/A',
                'total_commands_processed' => $info['total_commands_processed'] ?? 'N/A',
                'keyspace_hits' => $info['keyspace_hits'] ?? 'N/A',
                'keyspace_misses' => $info['keyspace_misses'] ?? 'N/A',
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(array $info): string
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return '0%';
        }
        
        return round(($hits / $total) * 100, 2) . '%';
    }
}
