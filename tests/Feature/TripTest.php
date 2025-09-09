<?php

use App\Models\User;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = auth()->login($this->user);
    $this->headers = ['Authorization' => 'Bearer ' . $this->token];
});

describe('Trip Creation', function () {
    it('can create a trip with valid data', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z',
            'distance' => 460,
            'trip_type' => 'business',
            'status' => 'in-progress',
            'total_seats' => 4
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'trip' => [
                        'id',
                        'user_id',
                        'origin',
                        'destination',
                        'start_time',
                        'end_time',
                        'status',
                        'distance',
                        'trip_type',
                        'total_seats',
                        'available_seats',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Trip created successfully',
                'data' => [
                    'trip' => [
                        'user_id' => $this->user->id,
                        'origin' => 'Paris',
                        'destination' => 'Lyon',
                        'distance' => 460,
                        'trip_type' => 'business',
                        'status' => 'in-progress',
                        'total_seats' => 4,
                        'available_seats' => 4
                    ]
                ]
            ]);

        // Verify trip was created in database
        $this->assertDatabaseHas('trips', [
            'user_id' => $this->user->id,
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'distance' => 460,
            'trip_type' => 'business',
            'status' => 'in-progress',
            'total_seats' => 4,
            'available_seats' => 4
        ]);
    });

    it('requires all mandatory fields for trip creation', function () {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['origin', 'destination', 'start_time', 'end_time']);
    });

    it('validates end_time is after start_time', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T10:00:00Z',
            'end_time' => '2024-01-15T08:00:00Z', // End before start
            'distance' => 460
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    });

    it('validates trip status enum values', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z',
            'status' => 'invalid-status'
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    it('validates trip_type enum values', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z',
            'trip_type' => 'invalid-type'
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trip_type']);
    });

    it('validates distance is non-negative', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z',
            'distance' => -100 // Negative distance
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['distance']);
    });

    it('validates total_seats is within valid range', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z',
            'total_seats' => 15 // Too many seats
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_seats']);
    });

    it('validates string length limits', function () {
        $tripData = [
            'origin' => str_repeat('a', 256), // Too long
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z'
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['origin']);
    });

    it('sets default values for optional fields', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z'
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'trip' => [
                        'trip_type' => 'personal',
                        'status' => 'in-progress',
                        'total_seats' => 4,
                        'available_seats' => 4
                    ]
                ]
            ]);
    });

    it('requires authentication to create trip', function () {
        $tripData = [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-01-15T08:00:00Z',
            'end_time' => '2024-01-15T10:00:00Z'
        ];

        $response = $this->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(401);
    });
});

describe('Trip Retrieval', function () {
    it('can get a specific trip', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v1/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trip' => [
                        'id' => $trip->id,
                        'user_id' => $this->user->id,
                        'origin' => $trip->origin,
                        'destination' => $trip->destination
                    ]
                ]
            ]);
    });

    it('can list all trips with pagination', function () {
        // Create multiple trips for the user
        Trip::factory()->count(10)->create(['user_id' => $this->user->id]);
        
        // Create trips for other users
        $otherUser = User::factory()->create();
        Trip::factory()->count(5)->create(['user_id' => $otherUser->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips?page=1&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [],
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links' => [],
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 15
                ]
            ]);

        // Verify we get 10 trips on first page
        expect($response->json('data.data'))->toHaveCount(10);
    });

    it('can filter trips by date range', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-31');

        // Create trips within date range
        Trip::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => '2024-01-15T08:00:00Z'
        ]);

        // Create trip outside date range
        Trip::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => '2024-02-15T08:00:00Z'
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v1/trips?start_date={$startDate->format('Y-m-d')}&end_date={$endDate->format('Y-m-d')}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 1
                ]
            ]);

        // Verify only one trip is returned
        expect($response->json('data.data'))->toHaveCount(1);
    });

    it('validates date range filter - end date must not exceed start date', function () {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips?start_date=2024-01-31&end_date=2024-01-01');

        // This should be handled by the controller logic
        $response->assertStatus(200);
        
        // The controller should return empty results when end_date < start_date
        expect($response->json('data.total'))->toBe(0);
    });

    it('can filter trips with only start date', function () {
        // Create trips before and after start date
        Trip::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => '2024-01-15T08:00:00Z'
        ]);

        Trip::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => '2024-02-15T08:00:00Z'
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips?start_date=2024-02-01');

        $response->assertStatus(200);
        
        // Should return trips from 2024-02-01 onwards
        expect($response->json('data.total'))->toBe(1);
    });

    it('can filter trips with only end date', function () {
        // Create trips before and after end date
        Trip::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => '2024-01-15T08:00:00Z'
        ]);

        Trip::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => '2024-02-15T08:00:00Z'
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips?end_date=2024-01-31');

        $response->assertStatus(200);
        
        // Should return trips up to 2024-01-31
        expect($response->json('data.total'))->toBe(1);
    });

    it('requires authentication to get trips', function () {
        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(401);
    });
});

describe('Trip Updates', function () {
    it('can update a trip with valid data', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $updateData = [
            'origin' => 'Updated Origin',
            'destination' => 'Updated Destination',
            'start_time' => '2024-02-01T08:00:00Z',
            'end_time' => '2024-02-01T10:00:00Z',
            'distance' => 500,
            'trip_type' => 'business',
            'status' => 'completed'
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v1/trips/{$trip->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trip updated successfully',
                'data' => [
                    'trip' => [
                        'id' => $trip->id,
                        'origin' => 'Updated Origin',
                        'destination' => 'Updated Destination',
                        'distance' => 500,
                        'trip_type' => 'business',
                        'status' => 'completed'
                    ]
                ]
            ]);

        // Verify trip was updated in database
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'origin' => 'Updated Origin',
            'destination' => 'Updated Destination',
            'distance' => 500,
            'trip_type' => 'business',
            'status' => 'completed'
        ]);
    });

    it('validates end_time is after start_time on update', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $updateData = [
            'start_time' => '2024-02-01T10:00:00Z',
            'end_time' => '2024-02-01T08:00:00Z' // End before start
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v1/trips/{$trip->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    });

    it('can partially update a trip', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $updateData = [
            'origin' => 'New Origin Only'
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v1/trips/{$trip->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'trip' => [
                        'origin' => 'New Origin Only',
                        'destination' => $trip->destination // Should remain unchanged
                    ]
                ]
            ]);
    });

    it('requires authentication to update trip', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/trips/{$trip->id}", [
            'origin' => 'Updated Origin'
        ]);

        $response->assertStatus(401);
    });
});

describe('Trip Deletion', function () {
    it('can delete a trip', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->headers)
            ->deleteJson("/api/v1/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trip deleted successfully'
            ]);

        // Verify trip was deleted from database
        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
    });

    it('requires authentication to delete trip', function () {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/trips/{$trip->id}");

        $response->assertStatus(401);
    });
});

describe('Trip Access Control', function () {
    it('users can view all trips but only update/delete their own', function () {
        $otherUser = User::factory()->create();
        $otherUserTrip = Trip::factory()->create(['user_id' => $otherUser->id]);

        // Users can view other users' trips
        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v1/trips/{$otherUserTrip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // But cannot update other users' trips
        $updateData = ['origin' => 'Hacked Origin'];
        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v1/trips/{$otherUserTrip->id}", $updateData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You can only update your own trips'
            ]);

        // And cannot delete other users' trips
        $response = $this->withHeaders($this->headers)
            ->deleteJson("/api/v1/trips/{$otherUserTrip->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You can only delete your own trips'
            ]);
    });

    it('users can see all trips in list including other users trips', function () {
        // Create trips for current user
        Trip::factory()->count(3)->create(['user_id' => $this->user->id]);

        // Create trips for other user
        $otherUser = User::factory()->create();
        Trip::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total' => 5 // All trips from all users
                ]
            ]);
    });
});

describe('Available Trips', function () {
    it('can get available trips for booking', function () {
        // Create trips with available seats
        Trip::factory()->create([
            'user_id' => $this->user->id,
            'total_seats' => 4,
            'available_seats' => 2,
            'status' => 'in-progress'
        ]);

        // Create trip with no available seats
        Trip::factory()->create([
            'user_id' => $this->user->id,
            'total_seats' => 4,
            'available_seats' => 0,
            'status' => 'in-progress'
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips/available');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // Should only return trips with available seats
        $trips = $response->json('data.data');
        foreach ($trips as $trip) {
            expect($trip['available_seats'])->toBeGreaterThan(0);
        }
    });
});

describe('Trip Not Found', function () {
    it('returns 404 for non-existent trip', function () {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/trips/99999');

        $response->assertStatus(404);
    });

    it('returns 404 when updating non-existent trip', function () {
        $response = $this->withHeaders($this->headers)
            ->putJson('/api/v1/trips/99999', ['origin' => 'Updated']);

        $response->assertStatus(404);
    });

    it('returns 404 when deleting non-existent trip', function () {
        $response = $this->withHeaders($this->headers)
            ->deleteJson('/api/v1/trips/99999');

        $response->assertStatus(404);
    });
});