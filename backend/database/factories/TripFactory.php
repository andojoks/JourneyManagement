<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-30 days', '+30 days');
        $endTime = Carbon::parse($startTime)->addHours(fake()->numberBetween(1, 8));

        $totalSeats = fake()->numberBetween(1, 8);
        
        return [
            'user_id' => User::factory(),
            'origin' => fake()->city(),
            'destination' => fake()->city(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => fake()->randomElement(['in-progress', 'completed', 'cancelled']),
            'distance' => fake()->optional()->randomFloat(2, 1, 1000),
            'trip_type' => fake()->randomElement(['personal', 'business']),
            'total_seats' => $totalSeats,
            'available_seats' => $totalSeats,
        ];
    }

    /**
     * Indicate that the trip is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the trip is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in-progress',
        ]);
    }

    /**
     * Indicate that the trip is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the trip is for business.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'trip_type' => 'business',
        ]);
    }

    /**
     * Indicate that the trip is personal.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'trip_type' => 'personal',
        ]);
    }

    /**
     * Create a trip with specific date range.
     */
    public function withDateRange(string $startDate, string $endDate): static
    {
        $startTime = fake()->dateTimeBetween($startDate, $endDate);
        $endTime = Carbon::parse($startTime)->addHours(fake()->numberBetween(1, 8));

        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }
}
