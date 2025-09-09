<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DynamicPricingService
{
    /**
     * Calculate dynamic pricing based on demand and other factors.
     */
    public function calculateSurgePricing(Trip $trip): array
    {
        $cacheKey = "pricing:trip:{$trip->id}";
        
        return Cache::remember($cacheKey, 120, function () use ($trip) { // 2 minutes cache
            $basePrice = $trip->base_price;
            $surgeMultiplier = $this->calculateSurgeMultiplier($trip);
            $finalPrice = $basePrice * $surgeMultiplier;

            return [
                'base_price' => $basePrice,
                'surge_multiplier' => $surgeMultiplier,
                'final_price' => round($finalPrice, 2),
                'factors' => $this->getPricingFactors($trip),
            ];
        });
    }

    /**
     * Calculate surge multiplier based on various factors.
     */
    private function calculateSurgeMultiplier(Trip $trip): float
    {
        $multiplier = 1.0;

        // Factor 1: Demand ratio (bookings vs available seats)
        $demandRatio = $this->calculateDemandRatio($trip);
        $multiplier += $demandRatio * 0.5; // Up to 50% increase based on demand

        // Factor 2: Time-based pricing (rush hours, weekends)
        $timeMultiplier = $this->getTimeBasedMultiplier($trip);
        $multiplier += $timeMultiplier;

        // Factor 3: Distance-based pricing
        $distanceMultiplier = $this->getDistanceBasedMultiplier($trip);
        $multiplier += $distanceMultiplier;

        // Factor 4: Weather conditions (if available)
        $weatherMultiplier = $this->getWeatherMultiplier($trip);
        $multiplier += $weatherMultiplier;

        // Factor 5: Special events or holidays
        $eventMultiplier = $this->getEventMultiplier($trip);
        $multiplier += $eventMultiplier;

        // Cap the multiplier between 1.0 and 3.0
        return max(1.0, min(3.0, $multiplier));
    }

    /**
     * Calculate demand ratio based on current bookings.
     */
    private function calculateDemandRatio(Trip $trip): float
    {
        $totalSeats = $trip->total_seats;
        $availableSeats = $trip->available_seats;
        $bookedSeats = $totalSeats - $availableSeats;

        if ($totalSeats === 0) {
            return 0;
        }

        $occupancyRate = $bookedSeats / $totalSeats;

        // Higher occupancy = higher demand
        if ($occupancyRate >= 0.9) {
            return 0.5; // 50% surge for 90%+ occupancy
        } elseif ($occupancyRate >= 0.7) {
            return 0.3; // 30% surge for 70%+ occupancy
        } elseif ($occupancyRate >= 0.5) {
            return 0.15; // 15% surge for 50%+ occupancy
        }

        return 0;
    }

    /**
     * Get time-based pricing multiplier.
     */
    private function getTimeBasedMultiplier(Trip $trip): float
    {
        $startTime = Carbon::parse($trip->start_time);
        $hour = $startTime->hour;
        $dayOfWeek = $startTime->dayOfWeek;

        $multiplier = 0;

        // Rush hour pricing (7-9 AM, 5-7 PM)
        if (($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19)) {
            $multiplier += 0.2; // 20% increase during rush hours
        }

        // Weekend pricing
        if ($dayOfWeek === 0 || $dayOfWeek === 6) { // Sunday or Saturday
            $multiplier += 0.15; // 15% increase on weekends
        }

        // Late night pricing (10 PM - 6 AM)
        if ($hour >= 22 || $hour <= 6) {
            $multiplier += 0.1; // 10% increase for late night trips
        }

        return $multiplier;
    }

    /**
     * Get distance-based pricing multiplier.
     */
    private function getDistanceBasedMultiplier(Trip $trip): float
    {
        $distance = $trip->distance;

        if ($distance <= 50) {
            return 0; // No additional charge for short trips
        } elseif ($distance <= 100) {
            return 0.05; // 5% increase for medium trips
        } elseif ($distance <= 200) {
            return 0.1; // 10% increase for long trips
        } else {
            return 0.15; // 15% increase for very long trips
        }
    }

    /**
     * Get weather-based pricing multiplier (placeholder for future implementation).
     */
    private function getWeatherMultiplier(Trip $trip): float
    {
        // This would integrate with a weather API in a real implementation
        // For now, return 0 (no weather-based pricing)
        return 0;
    }

    /**
     * Get event-based pricing multiplier.
     */
    private function getEventMultiplier(Trip $trip): float
    {
        $startTime = Carbon::parse($trip->start_time);
        
        // Check for major holidays (simplified)
        $holidays = [
            '12-25', // Christmas
            '01-01', // New Year
            '07-04', // Independence Day (US)
            '12-31', // New Year's Eve
        ];

        $dateString = $startTime->format('m-d');
        
        if (in_array($dateString, $holidays)) {
            return 0.3; // 30% increase on major holidays
        }

        return 0;
    }

    /**
     * Get detailed pricing factors for transparency.
     */
    private function getPricingFactors(Trip $trip): array
    {
        return [
            'demand_ratio' => $this->calculateDemandRatio($trip),
            'time_multiplier' => $this->getTimeBasedMultiplier($trip),
            'distance_multiplier' => $this->getDistanceBasedMultiplier($trip),
            'weather_multiplier' => $this->getWeatherMultiplier($trip),
            'event_multiplier' => $this->getEventMultiplier($trip),
            'occupancy_rate' => ($trip->total_seats - $trip->available_seats) / $trip->total_seats,
        ];
    }

    /**
     * Update trip pricing.
     */
    public function updateTripPricing(Trip $trip): Trip
    {
        $pricing = $this->calculateSurgePricing($trip);
        
        $trip->update([
            'surge_multiplier' => $pricing['surge_multiplier'],
            'final_price' => $pricing['final_price'],
        ]);

        return $trip->fresh();
    }

    /**
     * Get pricing for multiple trips with caching.
     */
    public function getBulkPricing(array $tripIds): array
    {
        $cacheKey = 'bulk_pricing:' . md5(implode(',', $tripIds));
        
        return Cache::remember($cacheKey, 120, function () use ($tripIds) { // 2 minutes cache
            $trips = Trip::whereIn('id', $tripIds)->get();
            $pricing = [];

            foreach ($trips as $trip) {
                $pricing[$trip->id] = $this->calculateSurgePricing($trip);
            }

            return $pricing;
        });
    }

    /**
     * Get cached pricing for a trip
     */
    public function getCachedPricing(int $tripId): ?array
    {
        $cacheKey = "pricing:trip:{$tripId}";
        return Cache::get($cacheKey);
    }

    /**
     * Invalidate pricing cache for a trip
     */
    public function invalidatePricingCache(int $tripId): void
    {
        $cacheKey = "pricing:trip:{$tripId}";
        Cache::forget($cacheKey);
        
        // Also invalidate bulk pricing caches that might contain this trip
        $this->invalidateBulkPricingCaches();
    }

    /**
     * Invalidate all bulk pricing caches
     */
    private function invalidateBulkPricingCaches(): void
    {
        try {
            $redis = Cache::getRedis();
            $keys = $redis->keys('bulk_pricing:*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to invalidate bulk pricing caches', ['error' => $e->getMessage()]);
        }
    }
}
