<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class OAuthClientAdminTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateWith(User $user): void
    {
        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');
    }

    private function createPasswordClient(string $name = 'Password Client'): Client
    {
        return app(ClientRepository::class)->createPasswordGrantClient($name, 'users', true);
    }

    public function test_manager_can_list_oauth_clients(): void
    {
        $manager = User::factory()->manager()->create();
        $this->createPasswordClient();

        $this->authenticateWith($manager);

        $this->getJson('/api/v1/oauth/clients')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'created_at']]])
            ->assertJsonCount(1, 'data');
    }

    public function test_list_does_not_expose_client_secrets(): void
    {
        $manager = User::factory()->manager()->create();
        $this->createPasswordClient();

        $this->authenticateWith($manager);

        $response = $this->getJson('/api/v1/oauth/clients');

        $response->assertOk();
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_non_manager_cannot_list_oauth_clients(): void
    {
        $user = User::factory()->create();

        $this->authenticateWith($user);

        $this->getJson('/api/v1/oauth/clients')->assertForbidden();
    }

    public function test_manager_can_create_oauth_client(): void
    {
        $manager = User::factory()->manager()->create();

        $this->authenticateWith($manager);

        $response = $this->postJson('/api/v1/oauth/clients', [
            'name' => 'My API Client',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['client_id', 'client_secret', 'name']]);

        $clientId = $response->json('data.client_id');

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $clientId,
            'name' => 'My API Client',
            'provider' => 'users',
            'revoked' => false,
        ]);

        $client = Client::find($clientId);

        $this->assertTrue($client->hasGrantType('password'));
        $this->assertTrue($client->hasGrantType('refresh_token'));
    }

    public function test_non_manager_cannot_create_oauth_client(): void
    {
        $user = User::factory()->create();

        $this->authenticateWith($user);

        $this->postJson('/api/v1/oauth/clients', [
            'name' => 'My API Client',
        ])->assertForbidden();
    }

    public function test_create_oauth_client_requires_name(): void
    {
        $manager = User::factory()->manager()->create();

        $this->authenticateWith($manager);

        $this->postJson('/api/v1/oauth/clients', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_manager_can_delete_oauth_client(): void
    {
        $manager = User::factory()->manager()->create();
        $client = $this->createPasswordClient();

        $this->authenticateWith($manager);

        $this->deleteJson("/api/v1/oauth/clients/{$client->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('oauth_clients', ['id' => $client->id]);
    }

    public function test_unauthenticated_user_cannot_access_oauth_clients(): void
    {
        $this->getJson('/api/v1/oauth/clients')->assertUnauthorized();
    }

    public function test_locked_user_cannot_obtain_passport_token(): void
    {
        $client = $this->createPasswordClient();
        User::factory()->locked()->create([
            'email' => 'locked-oauth@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => 'locked-oauth@example.com',
            'password' => 'password123',
            'scope' => '',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'invalid_grant']);
    }

    public function test_active_user_can_obtain_passport_token(): void
    {
        $client = $this->createPasswordClient();
        User::factory()->create([
            'email' => 'active-oauth@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => 'active-oauth@example.com',
            'password' => 'password123',
            'scope' => '',
        ])->assertOk()
            ->assertJsonStructure(['token_type', 'expires_in', 'access_token']);
    }
}
