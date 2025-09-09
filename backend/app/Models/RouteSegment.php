<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_waypoint_id',
        'to_waypoint_id',
        'distance',
        'estimated_time',
        'base_price',
        'is_active',
    ];

    protected $casts = [
        'distance' => 'decimal:2',
        'estimated_time' => 'integer',
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the starting waypoint.
     */
    public function fromWaypoint()
    {
        return $this->belongsTo(Waypoint::class, 'from_waypoint_id');
    }

    /**
     * Get the ending waypoint.
     */
    public function toWaypoint()
    {
        return $this->belongsTo(Waypoint::class, 'to_waypoint_id');
    }

    /**
     * Scope to get only active segments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
