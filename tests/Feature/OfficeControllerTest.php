<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApprovalNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
    ** @test
    */

    public function itShowsSpecificOffice()
    {
        $office = Office::factory()->create();
        $office->tags()->save(Tag::first());
        $office->images()->create(['path' => 'image.jpg']);
        Reservation::factory()->for($office)->create();


        $response = $this->get(
            '/api/offices/'. $office->id
        );

        $response->assertOk();
//        $response->dump();
    }


    /**
     ** @test
     */

    public function itCreatesAnOffice()
    {

        $user = User::factory()->createQuietly();
        $token = $user->createToken('testToken', ['office.create']);

        Notification::fake();

        $tag = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $response = $this->postJson('/api/offices', [
            'title' => 'testing office',
            'description' => 'testing office',
            'lat' => 10.5,
            'lng' => 10.5,
            'address_line1' => 'hmari',
            'price_per_day' => 1500,
            'monthly_discount' => 5,

            'tags' => [
                $tag->id, $tag2->id
            ]
        ], [
            'Authorization' => 'Bearer '.$token->plainTextToken
        ]);

        $response->assertStatus(201);

        Notification::assertSentTo(User::where('is_admin', true)->get(), OfficePendingApprovalNotification::class);

//        $response->dump();
    }

    /**
     ** @test
     */

    public function itUpdatesAnOffice()
    {
        $user1 = User::factory()->create();

        $office = Office::factory()->for($user1)->create();

        $token = $user1->createToken('newToken', ['office.update']);

        $response = $this->putJson('/api/offices/'. $office->id, [
            'title' => 'new amazing office',
            'description' => 'this is the updated amazing office',
        ],
        [
            'Authorization' => 'Bearer '. $token->plainTextToken
        ]);

        $response->assertOk();

//        $response->dump();
    }

/**
     ** @test
     */

    public function itMarksAnOfficeAsPending()
    {
        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $token = $user->createToken('newToken', ['office.update']);

        $response = $this->putJson('/api/offices/'. $office->id, [
            'lat' => '12',
            'lng' => '4.2',
        ],
        [
            'Authorization' => 'Bearer '. $token->plainTextToken
        ]);

        Notification::assertSentTo(User::where('is_admin', true)->get(), OfficePendingApprovalNotification::class);
        $response->assertOk();
//        $response->dump();
    }


    /**
     ** @test
     */

    public function itCanDeleteAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $token = $user->createToken('newToken', ['office.delete']);

        $response = $this->delete('/api/offices/'. $office->id, [],
            [
                'Authorization' => 'Bearer '. $token->plainTextToken
            ]);

        $this->assertSoftDeleted($office);
        $this->assertModelMissing($image);
        $response->assertOk();

    }

    /**
     ** @test
     */

    public function itCannotDeleteAnOfficeThatHasReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $reservations = Reservation::factory(3)->for($office)->create();

        $token = $user->createToken('newToken', ['office.delete']);

        $response = $this->delete('/api/offices/'. $office->id, [],
            [
                'Authorization' => 'Bearer '. $token->plainTextToken
            ]);

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'deleted_at' => null
        ]);

        // the one above is identical to
        $this->assertNotSoftDeleted($office);
        $response->assertStatus(302);

    }


    /**
     ** @test
     */

    public function itListsOfficesIncludingHiddenAndUnApprovedOfficesForTheOwner()
    {
        $user = User::factory()->create();
        $office = Office::factory(3)->for($user)->create();
        $office = Office::factory()->hidden()->for($user)->create();
        $office = Office::factory()->pending()->for($user)->create();

        $this->actingAs($user);
        $response = $this->get('/api/offices?user_id='. $user->id);

        $response->assertJsonCount(5, 'data');

    }
}
