<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function userWithQuestion(): User
    {
        return User::factory()->create([
            'email' => 'forgot@example.com',
            'security_question' => 'What is the name of your first pet?',
            'security_answer' => 'Rex',
        ]);
    }

    public function test_challenge_returns_the_security_question(): void
    {
        $this->userWithQuestion();

        $this->postJson('/api/v1/forgot-password/challenge', [
            'email' => 'forgot@example.com',
        ])->assertOk()
            ->assertJsonPath('data.question', 'What is the name of your first pet?');
    }

    public function test_challenge_returns_null_for_unknown_email(): void
    {
        $this->postJson('/api/v1/forgot-password/challenge', [
            'email' => 'nobody@example.com',
        ])->assertOk()
            ->assertJsonPath('data.question', null);
    }

    public function test_challenge_returns_null_when_no_question_is_set(): void
    {
        User::factory()->create(['email' => 'plain@example.com']);

        $this->postJson('/api/v1/forgot-password/challenge', [
            'email' => 'plain@example.com',
        ])->assertOk()
            ->assertJsonPath('data.question', null);
    }

    public function test_challenge_returns_null_for_a_locked_account(): void
    {
        User::factory()->locked()->create([
            'email' => 'locked@example.com',
            'security_question' => 'Question',
            'security_answer' => 'Answer',
        ]);

        $this->postJson('/api/v1/forgot-password/challenge', [
            'email' => 'locked@example.com',
        ])->assertOk()
            ->assertJsonPath('data.question', null);
    }

    public function test_reset_with_correct_answer_updates_the_password(): void
    {
        $user = $this->userWithQuestion();
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/forgot-password/reset', [
            'email' => 'forgot@example.com',
            'answer' => 'Rex',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertTrue(
            Hash::check('newpassword1', $user->fresh()->password)
        );

        $this->postJson('/api/v1/login', [
            'email' => 'forgot@example.com',
            'password' => 'password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email' => 'forgot@example.com',
            'password' => 'newpassword1',
        ])->assertOk();

        $this->withToken($token)
            ->withHeader('X-Auth-Method', 'sanctum')
            ->getJson('/api/v1/user')
            ->assertStatus(401);
    }

    public function test_reset_with_wrong_answer_is_rejected_uniformly(): void
    {
        $user = $this->userWithQuestion();

        $this->postJson('/api/v1/forgot-password/reset', [
            'email' => 'forgot@example.com',
            'answer' => 'Wrong',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('answer');

        $this->assertTrue(
            Hash::check('password', $user->fresh()->password)
        );
    }

    public function test_reset_for_unknown_email_returns_the_same_answer_error(): void
    {
        $this->postJson('/api/v1/forgot-password/reset', [
            'email' => 'nobody@example.com',
            'answer' => 'Whatever',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('answer');
    }

    public function test_reset_for_a_locked_account_returns_the_same_answer_error(): void
    {
        User::factory()->locked()->create([
            'email' => 'locked@example.com',
            'security_question' => 'Question',
            'security_answer' => 'Answer',
        ]);

        $this->postJson('/api/v1/forgot-password/reset', [
            'email' => 'locked@example.com',
            'answer' => 'Answer',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('answer');
    }

    public function test_reset_for_an_account_without_a_question_is_rejected(): void
    {
        User::factory()->create(['email' => 'plain@example.com']);

        $this->postJson('/api/v1/forgot-password/reset', [
            'email' => 'plain@example.com',
            'answer' => 'Whatever',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('answer');
    }

    public function test_reset_requires_password_confirmation(): void
    {
        $this->userWithQuestion();

        $this->postJson('/api/v1/forgot-password/reset', [
            'email' => 'forgot@example.com',
            'answer' => 'Rex',
            'password' => 'newpassword1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_register_stores_the_security_question_and_hashed_answer(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'security_question' => 'Birth city?',
            'security_answer' => 'Krakow',
        ])->assertStatus(201);

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertSame('Birth city?', $user->security_question);
        $this->assertTrue(Hash::check('Krakow', $user->security_answer));
    }

    public function test_register_rejects_an_answer_without_a_question(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'security_answer' => 'Orphan',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('security_answer');
    }
}
