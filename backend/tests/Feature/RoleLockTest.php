<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use Tests\TestCase;

class RoleLockTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateWith(
        User $user,
        string $method = 'sanctum'
    ): string {
        $token = $method === 'jwt'
            ? auth('jwt')->login($user)
            : $user->createToken('test')->plainTextToken;

        $this->withToken($token)->withHeader('X-Auth-Method', $method);

        return $token;
    }

    public function test_manager_can_list_users(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create();

        $this->authenticateWith($manager);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'status']]])
            ->assertJsonFragment(['id' => $target->id]);
    }

    public function test_non_manager_cannot_list_users(): void
    {
        $user = User::factory()->create();

        $this->authenticateWith($user);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_manager_can_lock_user_and_revokes_sanctum_tokens(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create();
        $targetToken = $target->createToken('test')->plainTextToken;

        $this->authenticateWith($target);
        $this->getJson('/api/v1/user')->assertOk();

        app('auth')->forgetGuards();

        $this->authenticateWith($manager);

        $this->postJson("/api/v1/users/{$target->id}/lock")
            ->assertOk()
            ->assertJson(['data' => ['id' => $target->id, 'status' => UserStatus::Locked->value]]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->id,
        ]);

        app('auth')->forgetGuards();

        $this->withToken($targetToken)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_manager_lock_revokes_passport_tokens(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create();

        $client = Client::factory()->create([
            'provider' => 'users',
            'grant_types' => ['password', 'refresh_token'],
        ]);

        $passportToken = Token::forceCreate([
            'id' => Str::random(40),
            'user_id' => $target->id,
            'client_id' => $client->id,
            'name' => 'oauth',
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addMinutes(60),
        ]);

        $this->authenticateWith($manager);

        $this->postJson("/api/v1/users/{$target->id}/lock")->assertOk();

        $this->assertTrue($passportToken->refresh()->revoked);
    }

    public function test_manager_can_unlock_user(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->locked()->create();

        $this->authenticateWith($manager);

        $this->postJson("/api/v1/users/{$target->id}/unlock")
            ->assertOk()
            ->assertJson(['data' => ['id' => $target->id, 'status' => UserStatus::Active->value]]);

        $this->assertTrue($target->refresh()->isActive());
    }

    public function test_manager_cannot_lock_self(): void
    {
        $manager = User::factory()->manager()->create();

        $this->authenticateWith($manager);

        $this->postJson("/api/v1/users/{$manager->id}/lock")->assertForbidden();
    }

    public function test_manager_cannot_change_own_role(): void
    {
        $manager = User::factory()->manager()->create();

        $this->authenticateWith($manager);

        $this->patchJson("/api/v1/users/{$manager->id}/role", [
            'role' => UserRole::User->value,
        ])->assertForbidden();
    }

    public function test_manager_can_promote_and_demote_user(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create();

        $this->authenticateWith($manager);

        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role' => UserRole::Manager->value,
        ])
            ->assertOk()
            ->assertJson(['data' => ['id' => $target->id, 'role' => UserRole::Manager->value]]);

        $this->assertTrue($target->refresh()->isManager());

        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role' => UserRole::User->value,
        ])->assertOk();

        $this->assertFalse($target->refresh()->isManager());
    }

    public function test_role_requires_valid_value(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create();

        $this->authenticateWith($manager);

        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role' => 'admin',
        ])->assertUnprocessable();
    }

    public function test_non_manager_cannot_lock_unlock_or_change_role(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->authenticateWith($user);

        $this->postJson("/api/v1/users/{$target->id}/lock")->assertForbidden();
        $this->postJson("/api/v1/users/{$target->id}/unlock")->assertForbidden();
        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role' => UserRole::Manager->value,
        ])->assertForbidden();
    }

    public function test_locked_user_jwt_blocked_on_protected_route(): void
    {
        $user = User::factory()->locked()->create();
        $token = auth('jwt')->login($user);

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->getJson('/api/v1/user')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Account is locked.',
            ]);
    }

    public function test_locked_user_cannot_refresh_sanctum_token(): void
    {
        $user = User::factory()->locked()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->postJson('/api/v1/sanctum/refresh')
            ->assertForbidden()
            ->assertJson(['message' => 'Account is locked.']);
    }

    public function test_locked_user_cannot_refresh_jwt_token(): void
    {
        $user = User::factory()->locked()->create();
        $token = auth('jwt')->login($user);

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'jwt')
            ->postJson('/api/v1/jwt/refresh')
            ->assertForbidden()
            ->assertJson(['message' => 'Account is locked.']);
    }

    public function test_locked_user_cannot_login_with_sanctum(): void
    {
        User::factory()->locked()->create([
            'email' => 'locked@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'locked@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_locked_user_cannot_login_with_jwt(): void
    {
        User::factory()->locked()->create([
            'email' => 'locked-jwt@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login/jwt', [
            'email' => 'locked-jwt@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_manager_can_reset_user_password(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('old-password'),
        ]);

        $this->authenticateWith($manager);

        $this->putJson("/api/v1/users/{$target->id}/password", [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJson(['data' => ['id' => $target->id, 'email' => 'reset@example.com']]);

        $this->assertTrue(Hash::check('new-password-123', $target->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $target->fresh()->password));
    }

    public function test_non_manager_cannot_reset_password(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->authenticateWith($user);

        $this->putJson("/api/v1/users/{$target->id}/password", [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertForbidden();
    }

    public function test_password_reset_requires_confirmation(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create();

        $this->authenticateWith($manager);

        $this->putJson("/api/v1/users/{$target->id}/password", [
            'password' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_password_allows_login_with_new_credentials(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->create([
            'email' => 'reset-login@example.com',
            'password' => bcrypt('old-password'),
        ]);

        $this->authenticateWith($manager);

        $this->putJson("/api/v1/users/{$target->id}/password", [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => 'reset-login@example.com',
            'password' => 'old-password',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/login', [
            'email' => 'reset-login@example.com',
            'password' => 'new-password-123',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }
}
