<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NewHostReservationNotification;
use App\Notifications\NewUserReservationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    public function itFiltersReservationsByDateRange()
    {
        $user = User::factory()->create();

        $from_date = '2024-03-03';
        $to_date = '2024-04-04';

        // within the date range
        $reservations = Reservation::factory()->for($user)->createMany([
            [
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15'
            ],
            [
                'start_date' => '2024-03-25',
                'end_date' => '2024-04-15'
            ],
            [
                'start_date' => '2024-03-15',
                'end_date' => '2024-03-19'
            ],
            [
                // starts before $from and ends after $to
                'start_date' => '2024-03-01',
                'end_date' => '2024-04-05'
            ]
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

//        $response = $this->getJson("/api/reservations?from_date={$from_date}&to_date={$to_date}");

        // the http build query instead of writing parameters manually
        $response = $this->getJson('/api/reservations?'. http_build_query([
            'from_date' => $from_date,
            'to_date' => $to_date,
            ]));

        $response->assertJsonCount(4, 'data');
        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());
    }



    /**
     * @test
     */

    public function itFiltersResultsByStatus()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE
        ]);

        $reservation2 = Reservation::factory()->cancelled()->for($user)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'. http_build_query([
                'status' => Reservation::STATUS_ACTIVE
            ]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }


    /**
     * @test
     */

    public function itFiltersReservationsByOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $reservations = Reservation::factory(2)->for($user)->for($office)->create();
        Reservation::factory(2)->for($user)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'. http_build_query([
            'office_id' => $office->id
            ]));

        $response->assertJsonCount(2, 'data');
        $this->assertEquals([$reservations[0]->id, $reservations[1]->id], collect($response->json('data'))->pluck('id')->toArray());

    }

    /**
     * @test
     */

    public function itMakesReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'price_per_day' => 1000,
            'monthly_discount' => 10
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(41)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.price', 36000)
            ->assertJsonPath('data.status', Reservation::STATUS_ACTIVE)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.office_id', $office->id);

    }

    /**
     * @test
     */

    public function itCannotMakeReservationOnOfficeThatDoesNotExist()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => 546,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(41)->toDateString(),
        ]);

        $response->assertUnprocessable();
    }


    /**
     * @test
     */

    public function itCannotMakeReservationOnOfficeThatBelongsToTheUser()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(41)->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'you cannot make a reservation on your office']);
    }


    /**
     * @test
     */

    public function itCannotMakeReservationForLessThan3Days()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'you cannot make a reservation for less than 3 days']);
    }

    /**
     * @test
     */

    public function itCanMakeReservationFor3Days()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        $response->assertCreated();
    }

    /**
     * @test
     */

    public function itCannotMakeConflictingReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        // I manually tested all conditions of conflicting by changing the number of added days each time
        $start_date = now()->addDays(1)->toDateString();
        $end_date = now()->addDays(16)->toDateString();

        Reservation::factory()->for($office)->create([
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(15)->toDateString()
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'you cannot make a reservation during this time']);
    }

    /**
     * @test
     */

    public function itCannotMakeReservationOnPendingOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'you cannot make a reservation on this office']);
    }

    /**
     * @test
     */

    public function itCannotMakeReservationOnHiddenOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'hidden' => true
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'you cannot make a reservation on this office']);
    }


    /**
     * @test
     */

    public function itSendsNotificationOnNewReservations()
    {
        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        Notification::assertSentTo($user, NewUserReservationNotification::class);
        Notification::assertSentTo($office->user, NewHostReservationNotification::class);

        $response->assertCreated();
    }
}
