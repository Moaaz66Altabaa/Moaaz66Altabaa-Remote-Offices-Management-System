<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @test
     */

    public function itListsReservationsThatBelongsToUser()
    {
        $user = User::factory()->create();
        Reservation::factory(2)->for($user)->create();

        Reservation::factory(2)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response->assertJsonCount(2, 'data');
    }

    /**
     * @test
     */

    public function itListsReservationsFilteredByDateRange()
    {
        $user = User::factory()->create();

        $from_date = '2024-03-03';
        $to_date = '2024-04-04';

        // within the date range
        $reservation1 = Reservation::factory()->for($user)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15'
        ]);

        $reservation2 = Reservation::factory()->for($user)->create([
            'start_date' => '2024-03-25',
            'end_date' => '2024-04-15'
        ]);

        $reservation3 = Reservation::factory()->for($user)->create([
            'start_date' => '2024-03-15',
            'end_date' => '2024-03-19'
        ]);


        // within the date range but belongs to a different user
        Reservation::factory()->create([
            'start_date' => '2024-03-15',
            'end_date' => '2024-03-19'
        ]);


        // outside the date range
        Reservation::factory()->for($user)->create([
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-19'
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2024-02-15',
            'end_date' => '2024-02-19'
        ]);
        $this->actingAs($user);

        DB::enableQueryLog();

        $response = $this->getJson('/api/reservations?'. http_build_query([
            'from_date' => $from_date,
            'to_date' => $to_date,
            ]));

        $response->assertJsonCount(3, 'data');
        $this->assertEquals([$reservation1->id, $reservation2->id, $reservation3->id], collect($response->json('data'))->pluck('id')->toArray());
    }

}
