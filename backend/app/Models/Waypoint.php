<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waypoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'city',
        'country',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get route segments starting from this waypoint.
     */
    public function outgoingSegments()
    {
        return $this->hasMany(RouteSegment::class, 'from_waypoint_id');
    }

    /**
     * Get route segments ending at this waypoint.
     */
    public function incomingSegments()
    {
        return $this->hasMany(RouteSegment::class, 'to_waypoint_id');
    }

    /**
     * Calculate distance to another waypoint using Haversine formula.
     */
    public function distanceTo(Waypoint $waypoint): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($waypoint->latitude);
        $lon2 = deg2rad($waypoint->longitude);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlon / 2) * sin($dlon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Find waypoint by name or city.
     */
    public static function findByNameOrCity(string $query)
    {
        return static::where('name', 'like', "%{$query}%")
            ->orWhere('city', 'like', "%{$query}%")
            ->first();
    }
}
