<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */

    public function itListsReservationsThatBelongsToUser()
    {
        $user = User::factory()->create();
        $reservations = Reservation::factory(2)->for($user)->create();

        $reservations = Reservation::factory(2)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response->assertJsonCount(2, 'data');
    }
}