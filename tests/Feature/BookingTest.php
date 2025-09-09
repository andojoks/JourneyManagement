<?php

use App\Models\User;
use App\Models\Trip;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->user3 = User::factory()->create();
    
    $this->trip1 = Trip::factory()->create([
        'user_id' => $this->user1->id,
        'total_seats' => 4,
        'available_seats' => 4,
    ]);
    
    $this->trip2 = Trip::factory()->create([
        'user_id' => $this->user2->id,
        'total_seats' => 2,
        'available_seats' => 2,
    ]);
    
    // Set up authentication headers for each user
    $this->user1Token = auth()->login($this->user1);
    $this->user2Token = auth()->login($this->user2);
    $this->user3Token = auth()->login($this->user3);
    
    $this->user1Headers = ['Authorization' => 'Bearer ' . $this->user1Token];
    $this->user2Headers = ['Authorization' => 'Bearer ' . $this->user2Token];
    $this->user3Headers = ['Authorization' => 'Bearer ' . $this->user3Token];
});

test('user can create a booking for available trip', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->postJson('/api/v1/bookings', [
            'trip_id' => $this->trip1->id,
            'seats_reserved' => 2,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Booking confirmed',
        ]);

    $this->assertDatabaseHas('bookings', [
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
        'status' => 'confirmed',
    ]);

    // Check that available seats were updated
    $this->trip1->refresh();
    expect($this->trip1->available_seats)->toBe(2);
});

test('user cannot book their own trip', function () {
    $response = $this->withHeaders($this->user1Headers)
        ->postJson('/api/v1/bookings', [
            'trip_id' => $this->trip1->id,
            'seats_reserved' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot book your own trip.',
        ]);
});

test('user cannot book more seats than available', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->postJson('/api/v1/bookings', [
            'trip_id' => $this->trip2->id,
            'seats_reserved' => 3,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Not enough seats available.',
        ]);
});

test('user cannot book same trip multiple times', function () {
    // Create first booking
    $this->withHeaders($this->user2Headers)
        ->postJson('/api/v1/bookings', [
            'trip_id' => $this->trip1->id,
            'seats_reserved' => 1,
        ]);

    // Try to create second booking for same trip
    $response = $this->withHeaders($this->user2Headers)
        ->postJson('/api/v1/bookings', [
            'trip_id' => $this->trip1->id,
            'seats_reserved' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'You already have a booking for this trip.',
        ]);
});

test('user can view their bookings', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
    ]);

    $response = $this->withHeaders($this->user2Headers)
        ->getJson('/api/v1/bookings');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonCount(1, 'data.data');
});

test('user can view specific booking', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    $response = $this->withHeaders($this->user2Headers)
        ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

test('user cannot view other users bookings unless they created the trip', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // User3 cannot view booking made by user2 on trip created by user1
    $response = $this->withHeaders($this->user3Headers)
        ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'You can only access bookings you made or trips you created',
        ]);
});

test('trip creator can view bookings made by others on their trip', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // User1 (trip creator) can view booking made by user2 on their trip
    $response = $this->withHeaders($this->user1Headers)
        ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

test('user can cancel their booking', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
    ]);

    // Update trip available seats to reflect the booking
    $this->trip1->update(['available_seats' => 2]);

    $response = $this->withHeaders($this->user2Headers)
        ->deleteJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Booking cancelled successfully',
        ]);

    // Check that booking status was updated
    $booking->refresh();
    expect($booking->status)->toBe('cancelled');

    // Check that available seats were restored
    $this->trip1->refresh();
    expect($this->trip1->available_seats)->toBe(4);
});

test('user cannot cancel other users bookings unless they created the trip', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // User3 cannot cancel booking made by user2 on trip created by user1
    $response = $this->withHeaders($this->user3Headers)
        ->deleteJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'You can only cancel bookings you made or trips you created',
        ]);
});

test('trip creator can cancel bookings made by others on their trip', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // Update trip available seats to reflect the booking
    $this->trip1->update(['available_seats' => 2]);

    // User1 (trip creator) can cancel booking made by user2 on their trip
    $response = $this->withHeaders($this->user1Headers)
        ->deleteJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Booking cancelled successfully',
        ]);

    // Check that booking status was updated
    $booking->refresh();
    expect($booking->status)->toBe('cancelled');

    // Check that available seats were restored
    $this->trip1->refresh();
    expect($this->trip1->available_seats)->toBe(4);
});

test('user cannot cancel already cancelled booking', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'status' => 'cancelled',
    ]);

    $response = $this->withHeaders($this->user2Headers)
        ->deleteJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Booking is already cancelled',
        ]);
});

test('booking validation works correctly', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->postJson('/api/v1/bookings', [
            'trip_id' => 999, // Non-existent trip
            'seats_reserved' => 0, // Invalid seats
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Validation errors',
        ])
        ->assertJsonHasKey('errors');
});

test('user can view available trips for booking', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->getJson('/api/v1/trips/available');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    // Should include all trips with available seats (including user's own trips)
    $responseData = $response->json('data.data');
    expect($responseData)->not->toBeEmpty();
});

test('available trips endpoint filters by date range', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->getJson('/api/v1/trips/available?start_date=2024-01-01&end_date=2024-12-31');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

test('trip creation includes seat capacity', function () {
    $response = $this->withHeaders($this->user1Headers)
        ->postJson('/api/v1/trips', [
            'origin' => 'Paris',
            'destination' => 'Lyon',
            'start_time' => '2024-12-01 10:00:00',
            'end_time' => '2024-12-01 14:00:00',
            'total_seats' => 6,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('trips', [
        'user_id' => $this->user1->id,
        'origin' => 'Paris',
        'destination' => 'Lyon',
        'total_seats' => 6,
        'available_seats' => 6,
    ]);
});

test('trip update can modify seat capacity', function () {
    $response = $this->withHeaders($this->user1Headers)
        ->putJson("/api/v1/trips/{$this->trip1->id}", [
            'total_seats' => 6,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $this->trip1->refresh();
    expect($this->trip1->total_seats)->toBe(6);
    expect($this->trip1->available_seats)->toBe(6);
});

test('trip update cannot set total seats less than reserved seats', function () {
    // Create a booking first
    Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
    ]);

    // Update trip to reflect the booking
    $this->trip1->update(['available_seats' => 2]);

    $response = $this->withHeaders($this->user1Headers)
        ->putJson("/api/v1/trips/{$this->trip1->id}", [
            'total_seats' => 1, // Less than reserved seats
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Total seats cannot be less than already reserved seats (2)',
        ]);
});

test('trip creator can view their trip without bookings', function () {
    // Create bookings from different users
    $booking1 = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 1,
    ]);
    
    $booking2 = Booking::factory()->create([
        'user_id' => $this->user3->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
    ]);

    $response = $this->withHeaders($this->user1Headers)
        ->getJson("/api/v1/trips/{$this->trip1->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $tripData = $response->json('data.trip');
    expect($tripData)->not->toHaveKey('bookings');
});

test('non-creator can view trip without bookings', function () {
    // Create bookings from different users
    $booking1 = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 1,
    ]);
    
    $booking2 = Booking::factory()->create([
        'user_id' => $this->user3->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
    ]);

    $response = $this->withHeaders($this->user2Headers)
        ->getJson("/api/v1/trips/{$this->trip1->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $tripData = $response->json('data.trip');
    expect($tripData)->not->toHaveKey('bookings');
});

test('user can view all trips in index endpoint', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->getJson('/api/v1/trips');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $tripsData = $response->json('data.data');
    expect($tripsData)->toHaveCount(2); // Both trips should be visible
});

test('returns 404 when booking not found', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->getJson('/api/v1/bookings/99999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Booking not found',
        ]);
});

test('returns 404 when trying to cancel non-existent booking', function () {
    $response = $this->withHeaders($this->user2Headers)
        ->deleteJson('/api/v1/bookings/99999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Booking not found',
        ]);
});

test('user can update their booking seats', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 1,
    ]);

    // Update trip available seats to reflect the booking
    $this->trip1->update(['available_seats' => 3]);

    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'seats_reserved' => 2
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Booking updated successfully',
        ]);

    // Check that booking was updated
    $booking->refresh();
    expect($booking->seats_reserved)->toBe(2);

    // Check that available seats were updated (3 - 1 = 2)
    $this->trip1->refresh();
    expect($this->trip1->available_seats)->toBe(2);
});

test('user can update booking status to cancelled', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'status' => 'confirmed',
    ]);

    // Update trip available seats to reflect the booking
    $this->trip1->update(['available_seats' => 3]);

    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'status' => 'cancelled'
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Booking updated successfully',
        ]);

    // Check that booking status was updated
    $booking->refresh();
    expect($booking->status)->toBe('cancelled');

    // Check that available seats were restored (3 + 1 = 4)
    $this->trip1->refresh();
    expect($this->trip1->available_seats)->toBe(4);
});

test('user can update booking status from cancelled to confirmed', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'status' => 'cancelled',
        'seats_reserved' => 2,
    ]);

    // Update trip available seats (all seats available since booking is cancelled)
    $this->trip1->update(['available_seats' => 4]);

    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'status' => 'confirmed'
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Booking updated successfully',
        ]);

    // Check that booking status was updated
    $booking->refresh();
    expect($booking->status)->toBe('confirmed');

    // Check that available seats were reduced (4 - 2 = 2)
    $this->trip1->refresh();
    expect($this->trip1->available_seats)->toBe(2);
});

test('user cannot update booking to exceed trip capacity', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 1,
    ]);

    // Create another booking to use up seats
    Booking::factory()->create([
        'user_id' => $this->user3->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 3,
        'status' => 'confirmed',
    ]);

    // Update trip available seats (4 total - 3 reserved = 1 available)
    $this->trip1->update(['available_seats' => 1]);

    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'seats_reserved' => 2 // This would exceed capacity (3 + 2 = 5 > 4)
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Not enough seats available. Requested seats would exceed trip capacity.',
        ]);
});

test('user cannot update booking status to confirmed if not enough seats', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'status' => 'cancelled',
        'seats_reserved' => 3,
    ]);

    // Create another booking to use up most seats
    Booking::factory()->create([
        'user_id' => $this->user3->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 2,
        'status' => 'confirmed',
    ]);

    // Update trip available seats (4 total - 2 reserved = 2 available)
    $this->trip1->update(['available_seats' => 2]);

    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'status' => 'confirmed' // This would need 3 seats but only 2 available
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Not enough seats available for this booking.',
        ]);
});

test('user cannot update other users bookings unless they created the trip', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // User3 cannot update booking made by user2 on trip created by user1
    $response = $this->withHeaders($this->user3Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'seats_reserved' => 2
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'You can only update bookings you made or trips you created',
        ]);
});

test('trip creator can update bookings made by others on their trip', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
        'seats_reserved' => 1,
    ]);

    // Update trip available seats to reflect the booking
    $this->trip1->update(['available_seats' => 3]);

    // User1 (trip creator) can update booking made by user2 on their trip
    $response = $this->withHeaders($this->user1Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'seats_reserved' => 2
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Booking updated successfully',
        ]);

    // Check that booking was updated
    $booking->refresh();
    expect($booking->seats_reserved)->toBe(2);
});

test('booking update validation works correctly', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // Test invalid seats_reserved
    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'seats_reserved' => 0
        ]);

    $response->assertStatus(422);

    // Test invalid status
    $response = $this->withHeaders($this->user2Headers)
        ->putJson("/api/v1/bookings/{$booking->id}", [
            'status' => 'invalid_status'
        ]);

    $response->assertStatus(422);
});

test('user can filter bookings by trip', function () {
    // Create bookings for user2 on different trips
    $booking1 = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);
    
    $booking2 = Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip2->id,
    ]);

    // Filter bookings by trip1
    $response = $this->withHeaders($this->user2Headers)
        ->getJson("/api/v1/bookings?trip_id={$this->trip1->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $bookingsData = $response->json('data.data');
    expect($bookingsData)->toHaveCount(1);
    expect($bookingsData[0]['trip_id'])->toBe($this->trip1->id);

    // Filter bookings by trip2
    $response = $this->withHeaders($this->user2Headers)
        ->getJson("/api/v1/bookings?trip_id={$this->trip2->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $bookingsData = $response->json('data.data');
    expect($bookingsData)->toHaveCount(1);
    expect($bookingsData[0]['trip_id'])->toBe($this->trip2->id);
});

test('user can filter bookings by non-existent trip', function () {
    // Create a booking for user2
    Booking::factory()->create([
        'user_id' => $this->user2->id,
        'trip_id' => $this->trip1->id,
    ]);

    // Filter by non-existent trip
    $response = $this->withHeaders($this->user2Headers)
        ->getJson("/api/v1/bookings?trip_id=99999");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $bookingsData = $response->json('data.data');
    expect($bookingsData)->toHaveCount(0);
});
