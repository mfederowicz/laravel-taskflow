<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Client;
use Tests\TestCase;

class MultiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_authenticates_with_explicit_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/tasks')
            ->assertOk();
    }

    public function test_jwt_authenticates_with_explicit_header(): void
    {
        $user = User::factory()->create();

        $token = auth('jwt')->login($user);

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->getJson('/api/tasks')
            ->assertOk();
    }

    public function test_passport_authenticates_with_explicit_header(): void
    {
        $user = User::factory()->create([
            'email' => 'passport@example.com',
            'password' => bcrypt('password123'),
        ]);

        $client = Client::factory()->create([
            'provider' => 'users',
            'grant_types' => ['password', 'refresh_token'],
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => 'passport@example.com',
            'password' => 'password123',
            'scope' => '',
        ]);
        $response->assertOk();

        $token = $response->json('access_token');

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'passport')
            ->getJson('/api/tasks')
            ->assertOk();
    }

    public function test_missing_auth_method_defaults_to_sanctum(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/tasks')
            ->assertOk();
    }

    public function test_invalid_auth_method_returns_unauthorized(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'something-invalid')
            ->getJson('/api/tasks')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }


}
