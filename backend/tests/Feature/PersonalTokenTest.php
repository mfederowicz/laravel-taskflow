<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalTokenTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSanctum(User $user): void
    {
        $this->withToken(
            $user->createToken('session', ['session'], now()->addMinutes(60))->plainTextToken
        )->withHeader('X-Auth-Method', 'sanctum');
    }

    public function test_user_can_create_a_personal_token(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $response = $this->postJson('/api/v1/user/tokens', ['name' => 'my-cli-token']);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'token' => ['id', 'name', 'expires_at', 'created_at'],
                    'plain_token',
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'my-cli-token',
        ]);
    }

    public function test_create_requires_a_name(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $this->postJson('/api/v1/user/tokens', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_list_returns_only_pat_tokens_not_session_tokens(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $user->createToken('My script', ['pat']);

        $response = $this->getJson('/api/v1/user/tokens');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('My script', $data[0]['name']);
    }

    public function test_list_does_not_expose_plain_token(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $user->createToken('secret-token', ['pat']);

        $response = $this->getJson('/api/v1/user/tokens');

        $this->assertStringNotContainsString('plain_token', $response->getContent());
    }

    public function test_pat_can_authenticate_api_requests(): void
    {
        $user = User::factory()->create();
        $pat = $user->createToken('ci-runner', ['pat'])->plainTextToken;

        $this->withToken($pat)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_user_can_revoke_own_pat(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $pat = $user->createToken('to-revoke', ['pat']);
        $tokenId = $pat->accessToken->id;

        $this->deleteJson("/api/v1/user/tokens/{$tokenId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_user_cannot_revoke_another_users_pat(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAsSanctum($other);

        $pat = $owner->createToken('owners-token', ['pat']);

        $this->deleteJson("/api/v1/user/tokens/{$pat->accessToken->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_revoke_a_session_token_via_pat_endpoint(): void
    {
        $user = User::factory()->create();
        $session = $user->createToken('session', ['session'], now()->addMinutes(60));
        $this->actingAsSanctum($user);

        $this->deleteJson("/api/v1/user/tokens/{$session->accessToken->id}")
            ->assertForbidden();
    }

    public function test_sanctum_refresh_rejects_pat(): void
    {
        $user = User::factory()->create();
        $pat = $user->createToken('my-pat', ['pat'])->plainTextToken;

        $this->withToken($pat)
            ->postJson('/api/v1/sanctum/refresh')
            ->assertUnauthorized();
    }

    public function test_pat_is_revoked_when_user_is_locked(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->create();
        $pat = $user->createToken('my-pat', ['pat'])->plainTextToken;

        $this->withToken($manager->createToken('session', ['session'], now()->addMinutes(60))->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson("/api/v1/users/{$user->id}/lock")
            ->assertOk();

        app('auth')->forgetGuards();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $this->withToken($pat)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_pat_can_be_created_with_expiry(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $expiresAt = now()->addDays(30)->toDateString();

        $response = $this->postJson('/api/v1/user/tokens', [
            'name' => 'short-lived-token',
            'expires_at' => $expiresAt,
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.token.expires_at'));
    }

    public function test_pat_expires_at_must_be_in_the_future(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $this->postJson('/api/v1/user/tokens', [
            'name' => 'bad-token',
            'expires_at' => now()->subDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    }

    public function test_pat_without_expiry_has_null_expires_at(): void
    {
        $user = User::factory()->create();
        $this->actingAsSanctum($user);

        $response = $this->postJson('/api/v1/user/tokens', ['name' => 'no-expiry-token']);

        $response->assertCreated();
        $this->assertNull($response->json('data.token.expires_at'));
    }

    public function test_expired_pat_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $pat = $user->createToken('expired-pat', ['pat'], now()->addMinutes(5))->plainTextToken;

        $this->travel(10)->minutes();
        app('auth')->forgetGuards();

        $this->withToken($pat)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }
}
