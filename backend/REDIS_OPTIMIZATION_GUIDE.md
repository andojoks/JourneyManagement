# Redis Query Optimization Guide

This guide explains the Redis caching optimizations implemented in the Journey Manager application.

## Overview

The application now uses Redis for intelligent query caching to improve performance and reduce database load. The caching system includes:

- **Model-level caching** for frequently accessed data
- **Service-level caching** for complex calculations
- **Controller-level caching** for API responses
- **Automatic cache invalidation** when data changes

## Cache TTL (Time To Live) Settings

| Cache Type | TTL | Reason |
|------------|-----|--------|
| Trip Listings | 5 minutes | Frequently changing data |
| Available Trips | 3 minutes | Real-time availability |
| Pricing Data | 2 minutes | Dynamic pricing |
| Route Calculations | 1 hour | Rarely change |
| Waypoint Search | 30 minutes | Static data |
| User Data | 10 minutes | User-specific data |

## Usage Examples

### 1. Using Cached Trip Data

```php
// Get cached trips with filters
$trips = Trip::getCachedTrips(['start_date' => $date], $userId);

// Get cached available trips
$availableTrips = Trip::getCachedAvailableTrips(['origin' => 'New York']);

// Get cached trip details
$trip = Trip::getCachedTrip($tripId);
```

### 2. Using Cached Pricing

```php
// Get cached pricing for a trip
$pricing = app(DynamicPricingService::class)->getCachedPricing($tripId);

// Get bulk pricing with caching
$pricing = app(DynamicPricingService::class)->getBulkPricing([1, 2, 3]);
```

### 3. Using Cached Routes

```php
// Get cached route between waypoints
$route = app(RouteOptimizationService::class)->getCachedRoute($startId, $endId);

// Get cached route between cities
$route = app(RouteOptimizationService::class)->getRouteBetweenCities('New York', 'Boston');
```

## Cache Management Commands

### Warm Up Caches
```bash
php artisan cache:manage warm
```

### Clear All Caches
```bash
php artisan cache:manage clear
```

### Show Cache Statistics
```bash
php artisan cache:manage stats
```

### Invalidate Specific Caches
```bash
php artisan cache:manage invalidate
```

## Automatic Cache Invalidation

The system automatically invalidates caches when data changes:

- **Trip changes**: Invalidates trip-related caches
- **Booking changes**: Invalidates booking and trip caches
- **Waypoint changes**: Invalidates waypoint and route caches

## Performance Benefits

1. **Reduced Database Load**: Frequently accessed data served from Redis
2. **Faster Response Times**: Cached queries return in milliseconds
3. **Better Scalability**: Redis handles high concurrent requests
4. **Intelligent Invalidation**: Caches cleared only when necessary

## Monitoring Cache Performance

Use the cache statistics command to monitor performance:

```bash
php artisan cache:manage stats
```

Look for:
- **Hit Rate**: Should be above 80% for optimal performance
- **Memory Usage**: Monitor Redis memory consumption
- **Command Processing**: Track Redis activity

## Best Practices

1. **Use appropriate TTL**: Balance between performance and data freshness
2. **Monitor hit rates**: Low hit rates indicate poor cache utilization
3. **Warm up caches**: Pre-populate frequently accessed data
4. **Invalidate selectively**: Only clear relevant caches when data changes

## Troubleshooting

### Low Cache Hit Rate
- Check if cache keys are consistent
- Verify TTL settings are appropriate
- Consider warming up caches

### High Memory Usage
- Review cache TTL settings
- Clear unused caches
- Monitor cache key patterns

### Stale Data
- Check cache invalidation logic
- Verify model event handlers
- Clear specific caches manually

## Configuration

Redis configuration is in `config/database.php`:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'persistent' => env('REDIS_PERSISTENT', false),
    ],
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_DB', '0'),
    ],
],
```

## Environment Variables

Make sure these are set in your `.env` file:

```env
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=yourpassword
REDIS_DB=0
```
