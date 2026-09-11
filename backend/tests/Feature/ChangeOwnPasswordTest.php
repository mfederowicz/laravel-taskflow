<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangeOwnPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertFalse(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_new_password_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_new_password_min_length_is_enforced(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_uses_new_password_after_change(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'old-password-123',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(401);
    }
}
