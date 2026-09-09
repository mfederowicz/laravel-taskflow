<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum access tokens become invalid once the configured
     * expiration (minutes) has passed since they were created.
     */
    public function test_sanctum_token_expires_after_configured_ttl(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertOk();

        $this->travel(61)->minutes();

        app('auth')->forgetGuards();

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * An expired Sanctum token can be rotated within the refresh window;
     * the old token row is deleted and the fresh token works.
     */
    public function test_sanctum_token_can_be_refreshed_within_window(): void
    {
        $user = User::factory()->create();
        $tokenModel = $user->createToken('test');
        $token = $tokenModel->plainTextToken;

        $this->travel(61)->minutes();

        $response = $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson('/api/v1/sanctum/refresh');

        $response->assertOk()
            ->assertJson(['data' => ['user' => ['id' => $user->id]]])
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $newToken = $response->json('data.token');
        $this->assertNotSame($token, $newToken);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenModel->accessToken->id,
        ]);

        $this->withToken($newToken)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertOk();
    }

    public function test_sanctum_refresh_fails_beyond_window(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->travel(8)->days();

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson('/api/v1/sanctum/refresh')
            ->assertUnauthorized();
    }

    public function test_sanctum_refresh_rejects_invalid_token(): void
    {
        $this->withToken('nope|invalid')
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson('/api/v1/sanctum/refresh')
            ->assertUnauthorized();
    }

    /**
     * An expired JWT can be exchanged for a fresh one within the refresh
     * window; the fresh token authenticates subsequent requests.
     */
    public function test_jwt_token_can_be_refreshed_after_expiry(): void
    {
        $user = User::factory()->create();
        $token = auth('jwt')->login($user);

        $this->travel(61)->minutes();
        app('auth')->forgetGuards();

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $response = $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh');

        $response->assertOk()
            ->assertJson(['data' => ['user' => ['id' => $user->id]]])
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->withToken($response->json('data.token'))
            ->withHeader('X-Auth-Method', 'jwt')
            ->getJson('/api/v1/user')
            ->assertOk();
    }

    public function test_jwt_refresh_fails_after_refresh_window(): void
    {
        app('tymon.jwt.validators.payload')->setRefreshTTL(-1);

        $user = User::factory()->create();
        $token = auth('jwt')->login($user);

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh')
            ->assertUnauthorized();
    }

    public function test_jwt_refresh_rejects_invalid_token(): void
    {
        $this->withToken('not-a-valid-jwt')
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh')
            ->assertUnauthorized();
    }

    /**
     * Passport stores explicit expirations on both the access and the
     * refresh token, honoring the configured lifetimes.
     */
    public function test_passport_tokens_issued_with_configured_lifetimes(): void
    {
        $user = User::factory()->create([
            'email' => 'passport-expiry@example.com',
            'password' => bcrypt('password123'),
        ]);

        $client = Client::factory()->create([
            'provider' => 'users',
            'grant_types' => ['password', 'refresh_token'],
        ]);

        $this->postJson('/api/v1/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => 'passport-expiry@example.com',
            'password' => 'password123',
            'scope' => '',
        ])->assertOk();

        $accessToken = Token::where('user_id', $user->id)->sole();
        $this->assertNotNull($accessToken->expires_at);
        $this->assertTrue(
            $accessToken->expires_at->greaterThan(now()->addMinutes(59))
        );
        $this->assertTrue(
            $accessToken->expires_at->lessThan(now()->addMinutes(61))
        );

        $refreshToken = RefreshToken::first();
        $this->assertNotNull($refreshToken->expires_at);
        $this->assertTrue(
            $refreshToken->expires_at->greaterThan(now()->addDays(6)->addHours(23))
        );
        $this->assertTrue(
            $refreshToken->expires_at->lessThan(now()->addDays(7)->addHours(1))
        );
    }
}
