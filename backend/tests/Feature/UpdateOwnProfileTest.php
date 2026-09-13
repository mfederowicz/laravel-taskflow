<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateOwnProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_own_email_is_allowed_to_stay_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => 'Renamed',
            'email' => 'keep@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_email_taken_by_another_user_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => 'New Name',
            'email' => 'taken@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'mine@example.com',
        ]);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => '',
            'email' => $user->email,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_email_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => 'New Name',
            'email' => 'not-an-email',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->putJson('/api/v1/user/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])->assertStatus(401);
    }

    public function test_user_can_set_a_security_question_and_answer(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'security_question' => 'Favorite color?',
            'security_answer' => 'Green',
        ])->assertOk();

        $fresh = $user->fresh();

        $this->assertSame('Favorite color?', $fresh->security_question);
        $this->assertNotSame('Green', $fresh->security_answer);
        $this->assertTrue(Hash::check('Green', $fresh->security_answer));
    }

    public function test_a_plain_profile_save_keeps_the_existing_security_question(): void
    {
        $user = User::factory()->create([
            'security_question' => 'Favorite color?',
            'security_answer' => 'Green',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => 'Renamed',
            'email' => $user->email,
            'security_question' => '',
            'security_answer' => '',
        ])->assertOk();

        $fresh = $user->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame('Favorite color?', $fresh->security_question);
        $this->assertTrue(Hash::check('Green', $fresh->security_answer));
    }

    public function test_explicit_clear_removes_the_security_question_and_answer(): void
    {
        $user = User::factory()->create([
            'security_question' => 'Favorite color?',
            'security_answer' => 'Green',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'security_question' => '',
            'security_answer' => '',
            'clear_security_question' => true,
        ])->assertOk();

        $fresh = $user->fresh();

        $this->assertNull($fresh->security_question);
        $this->assertNull($fresh->security_answer);
    }

    public function test_an_answer_requires_a_security_question(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->withHeader('X-Auth-Method', 'sanctum');

        $this->putJson('/api/v1/user/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'security_question' => '',
            'security_answer' => 'Orphan',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('security_answer');
    }
}
