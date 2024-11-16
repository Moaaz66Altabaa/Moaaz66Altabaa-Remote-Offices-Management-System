<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */

    public function itUploadsAnImageAndStoreItUnderAnOffice(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->postJson("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::assertExists(
            $response->json('data.path')
        );
    }


    /**
     * @test
     */

    public function itUpdatesTheFeaturedImageOfAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'. $office->id,
        [
            'featured_image_id' => $image->id
        ]);

        $response->assertOk()
            ->assertJsonPath( 'data.featured_image_id', $image->id);
    }


    /**
     * @test
     */

    public function itCannotUpdateTheFeaturedImageThatDoesNotBelongToTheOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'. $office2->id,
        [
            'featured_image_id' => $image->id
        ]);

        $response->assertUnprocessable()
            ->assertInvalid('featured_image_id');
    }

    /**
     * @test
     */

    public function itDeletesAnImage()
    {
        Storage::put('/office_image.jpg', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);


        $image1 = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertOk();
        $this->assertModelMissing($image);
        Storage::assertMissing('office_image.jpg');
    }

    /**
     * @test
     */

    public function itDoesNotDeleteTheOnlyImage()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertJsonValidationErrors(['image' => 'cannot delete the only image!']);
        $this->assertModelExists($image);
        $response->assertStatus(422);
    }

    /**
     * @test
     */

    public function itDoesNotDeleteTheFeaturedImage()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);


        $image1 = $office->images()->create([
            'path' => 'image1.jpg'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertJsonValidationErrors(['image' => 'cannot delete the featured image!']);
        $this->assertModelExists($image);
        $response->assertStatus(422);
    }

    /**
     * @test
     */

    public function itDoesNotDeleteAnImageThatBelongsToAnotherResource()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office2 = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office2->id}/images/{$image->id}");

        $response->assertNotFound();
        $this->assertModelExists($image);
    }
}
