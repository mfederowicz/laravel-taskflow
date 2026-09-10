<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

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
     * The Sanctum refresh window slides: a rotated token gets a fresh
     * created_at, so it can be rotated again after the next expiry.
     */
    public function test_sanctum_refresh_window_slides_on_rotation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->travel(61)->minutes();

        $response = $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson('/api/v1/sanctum/refresh')
            ->assertOk();

        $secondToken = $response->json('data.token');

        $this->travel(61)->minutes();

        $this->withToken($secondToken)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson('/api/v1/sanctum/refresh')
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
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
     * A rotated JWT has a fresh iat, so repeated expiries keep resolving
     * through the refresh endpoint as long as each refresh happens within
     * the configured window.
     */
    public function test_jwt_token_can_be_refreshed_multiple_times(): void
    {
        $user = User::factory()->create();
        $token = auth('jwt')->login($user);

        $this->travel(61)->minutes();
        app('auth')->forgetGuards();

        $response = $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh')
            ->assertOk();

        $secondToken = $response->json('data.token');

        $this->travel(61)->minutes();
        app('auth')->forgetGuards();

        $this->withToken($secondToken)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh')
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
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

    /**
     * A blacklisted JWT cannot be refreshed — returns 401 instead of 500.
     */
    public function test_jwt_refresh_rejects_blacklisted_token(): void
    {
        $user = User::factory()->create();
        $token = auth('jwt')->login($user);

        // Blacklist the token (simulates logout).
        JWTAuth::setToken($token)->invalidate();

        $this->travel(61)->minutes();
        app('auth')->forgetGuards();

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ]);
    }

    /**
     * When JWT_SECRET is empty, login returns 401 — not a 500.
     */
    public function test_jwt_login_handles_missing_secret(): void
    {
        User::factory()->create([
            'email' => 'jwt-nosecret@example.com',
            'password' => bcrypt('password123'),
        ]);

        config(['jwt.secret' => '']);

        $this->postJson('/api/v1/login/jwt', [
            'email' => 'jwt-nosecret@example.com',
            'password' => 'password123',
        ])->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Logging out with an already-invalid JWT does not throw — returns 204.
     */
    public function test_jwt_logout_with_already_invalid_token(): void
    {
        $user = User::factory()->create();
        $token = auth('jwt')->login($user);

        // Invalidate the token first.
        JWTAuth::setToken($token)->invalidate();

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/logout')
            ->assertNoContent();
    }
}
