<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TagsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     ** @test
     */

    public function itListsTags()
    {
        $response = $this->get('/api/tags');

        $response->assertStatus(200);

        $this->assertNotNull($response->json('data')[0]['id']);
    }
}
