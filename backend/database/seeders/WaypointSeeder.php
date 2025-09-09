<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Waypoint;
use App\Models\RouteSegment;

class WaypointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create major cities as waypoints
        $waypoints = [
            ['name' => 'Paris Central', 'latitude' => 48.8566, 'longitude' => 2.3522, 'city' => 'Paris', 'country' => 'France'],
            ['name' => 'Lyon Central', 'latitude' => 45.7640, 'longitude' => 4.8357, 'city' => 'Lyon', 'country' => 'France'],
            ['name' => 'Marseille Central', 'latitude' => 43.2965, 'longitude' => 5.3698, 'city' => 'Marseille', 'country' => 'France'],
            ['name' => 'Toulouse Central', 'latitude' => 43.6047, 'longitude' => 1.4442, 'city' => 'Toulouse', 'country' => 'France'],
            ['name' => 'Nice Central', 'latitude' => 43.7102, 'longitude' => 7.2620, 'city' => 'Nice', 'country' => 'France'],
            ['name' => 'Nantes Central', 'latitude' => 47.2184, 'longitude' => -1.5536, 'city' => 'Nantes', 'country' => 'France'],
            ['name' => 'Strasbourg Central', 'latitude' => 48.5734, 'longitude' => 7.7521, 'city' => 'Strasbourg', 'country' => 'France'],
            ['name' => 'Montpellier Central', 'latitude' => 43.6110, 'longitude' => 3.8767, 'city' => 'Montpellier', 'country' => 'France'],
            ['name' => 'Bordeaux Central', 'latitude' => 44.8378, 'longitude' => -0.5792, 'city' => 'Bordeaux', 'country' => 'France'],
            ['name' => 'Lille Central', 'latitude' => 50.6292, 'longitude' => 3.0573, 'city' => 'Lille', 'country' => 'France'],
        ];

        foreach ($waypoints as $waypointData) {
            Waypoint::create($waypointData);
        }

        // Create route segments between major cities
        $waypointIds = Waypoint::pluck('id', 'city')->toArray();
        
        $routes = [
            // Paris connections
            ['from' => 'Paris', 'to' => 'Lyon', 'distance' => 460, 'time' => 280, 'price' => 45.00],
            ['from' => 'Paris', 'to' => 'Marseille', 'distance' => 770, 'time' => 480, 'price' => 75.00],
            ['from' => 'Paris', 'to' => 'Toulouse', 'distance' => 680, 'time' => 420, 'price' => 65.00],
            ['from' => 'Paris', 'to' => 'Nantes', 'distance' => 380, 'time' => 240, 'price' => 35.00],
            ['from' => 'Paris', 'to' => 'Strasbourg', 'distance' => 490, 'time' => 300, 'price' => 50.00],
            ['from' => 'Paris', 'to' => 'Bordeaux', 'distance' => 580, 'time' => 360, 'price' => 55.00],
            ['from' => 'Paris', 'to' => 'Lille', 'distance' => 220, 'time' => 150, 'price' => 25.00],
            
            // Lyon connections
            ['from' => 'Lyon', 'to' => 'Marseille', 'distance' => 320, 'time' => 200, 'price' => 30.00],
            ['from' => 'Lyon', 'to' => 'Nice', 'distance' => 470, 'time' => 300, 'price' => 45.00],
            ['from' => 'Lyon', 'to' => 'Montpellier', 'distance' => 300, 'time' => 180, 'price' => 28.00],
            
            // Marseille connections
            ['from' => 'Marseille', 'to' => 'Nice', 'distance' => 200, 'time' => 120, 'price' => 20.00],
            ['from' => 'Marseille', 'to' => 'Montpellier', 'distance' => 170, 'time' => 100, 'price' => 18.00],
            
            // Toulouse connections
            ['from' => 'Toulouse', 'to' => 'Montpellier', 'distance' => 240, 'time' => 150, 'price' => 22.00],
            ['from' => 'Toulouse', 'to' => 'Bordeaux', 'distance' => 250, 'time' => 160, 'price' => 24.00],
            
            // Nantes connections
            ['from' => 'Nantes', 'to' => 'Bordeaux', 'distance' => 340, 'time' => 220, 'price' => 32.00],
        ];

        foreach ($routes as $routeData) {
            $fromId = $waypointIds[$routeData['from']];
            $toId = $waypointIds[$routeData['to']];
            
            RouteSegment::create([
                'from_waypoint_id' => $fromId,
                'to_waypoint_id' => $toId,
                'distance' => $routeData['distance'],
                'estimated_time' => $routeData['time'],
                'base_price' => $routeData['price'],
                'is_active' => true,
            ]);

            // Create reverse route
            RouteSegment::create([
                'from_waypoint_id' => $toId,
                'to_waypoint_id' => $fromId,
                'distance' => $routeData['distance'],
                'estimated_time' => $routeData['time'],
                'base_price' => $routeData['price'],
                'is_active' => true,
            ]);
        }
    }
}
