<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office_id' => Office::factory(),
            'price' => $this->faker->numberBetween(100, 10000),
            'start_date' => $this->faker->date,
            'end_date' => $this->faker->date,
        ];
    }

    public function cancelled(): Factory
    {
        return $this->state([
            'status' => Reservation::STATUS_CANCELLED
        ]);
    }
}
