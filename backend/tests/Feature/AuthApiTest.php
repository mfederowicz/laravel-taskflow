<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_user_can_register_and_receives_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->withToken($response->json('data.token'))
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertOk();
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_sanctum(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['id'], 'token']]);

        $this->withToken($response->json('data.token'))
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertOk();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_jwt(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login/jwt', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson(['data' => ['user' => ['id' => $user->id]]])
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->withToken($response->json('data.token'))
            ->withHeader('X-Auth-Method', 'jwt')
            ->getJson('/api/v1/user')
            ->assertOk();
    }

    public function test_jwt_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login/jwt', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }
}
