<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    /**
     * @test
     */

    public function itUploadsAnImageAndStoreItUnderAnOffice(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->postJson("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::disk('public')->assertExists(
            $response->json('data.path')
        );
    }
}
