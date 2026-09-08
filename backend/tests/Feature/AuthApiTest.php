<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_passport_client_helper_does_not_accumulate_clients(): void
    {
        $this->getJson('/api/v1/oauth/client')
            ->assertOk()
            ->assertJsonStructure(['client_id', 'client_secret']);

        $this->getJson('/api/v1/oauth/client')->assertOk();

        $this->assertSame(
            1,
            Client::where('name', AuthController::PASSPORT_CLIENT_NAME)->count()
        );
    }

    public function test_passport_client_helper_keeps_manually_created_clients(): void
    {
        $manualClient = Client::factory()->create([
            'name' => 'My manual client',
            'provider' => 'users',
            'grant_types' => ['password', 'refresh_token'],
        ]);

        $this->getJson('/api/v1/oauth/client')->assertOk();

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $manualClient->id,
            'name' => 'My manual client',
        ]);
    }
}
