<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_comments_for_their_task(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        $task = Task::factory()
            ->for($user)
            ->for($project)
            ->create();

        Comment::factory()
            ->count(3)
            ->for($user)
            ->for($task)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_comment_on_their_task(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        $task = Task::factory()
            ->for($user)
            ->for($project)
            ->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/v1/tasks/{$task->id}/comments",
            [
                'body' => 'Looks good to me.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.body', 'Looks good to me.')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'Looks good to me.',
        ]);
    }

    public function test_user_cannot_view_comments_for_another_users_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = Project::factory()
            ->for($owner)
            ->create();

        $task = Task::factory()
            ->for($owner)
            ->for($project)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertForbidden();
    }

    public function test_user_cannot_create_comment_on_another_users_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = Project::factory()
            ->for($owner)
            ->create();

        $task = Task::factory()
            ->for($owner)
            ->for($project)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->postJson(
            "/api/v1/tasks/{$task->id}/comments",
            [
                'body' => 'Unauthorized comment.',
            ]
        )->assertForbidden();
    }

    public function test_comment_body_is_required(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        $task = Task::factory()
            ->for($user)
            ->for($project)
            ->create();

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/tasks/{$task->id}/comments",
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'body',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_comments(): void
    {
        $task = Task::factory()->create();

        $this->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_can_view_a_single_comment(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()
            ->for($user)
            ->create();

        $comment = Comment::factory()
            ->for($user)
            ->for($task)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/tasks/{$task->id}/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $comment->id)
            ->assertJsonPath('data.body', $comment->body);
    }

    public function test_user_can_update_their_comment(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()
            ->for($user)
            ->create();

        $comment = Comment::factory()
            ->for($user)
            ->for($task)
            ->create();

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/v1/tasks/{$task->id}/comments/{$comment->id}",
            ['body' => 'Updated body.']
        )
            ->assertOk()
            ->assertJsonPath('data.body', 'Updated body.');

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Updated body.',
        ]);
    }

    public function test_user_cannot_update_another_users_comment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = Task::factory()
            ->for($owner)
            ->create();

        $comment = Comment::factory()
            ->for($owner)
            ->for($task)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->putJson(
            "/api/v1/tasks/{$task->id}/comments/{$comment->id}",
            ['body' => 'Hijacked.']
        )->assertForbidden();
    }

    public function test_user_can_delete_their_comment(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()
            ->for($user)
            ->create();

        $comment = Comment::factory()
            ->for($user)
            ->for($task)
            ->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/tasks/{$task->id}/comments/{$comment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = Task::factory()
            ->for($owner)
            ->create();

        $comment = Comment::factory()
            ->for($owner)
            ->for($task)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->deleteJson("/api/v1/tasks/{$task->id}/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_update_comment_requires_a_body(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()
            ->for($user)
            ->create();

        $comment = Comment::factory()
            ->for($user)
            ->for($task)
            ->create();

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/v1/tasks/{$task->id}/comments/{$comment->id}",
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'body',
            ]);
    }

    public function test_comment_must_belong_to_the_task(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        $taskA = Task::factory()
            ->for($user)
            ->for($project)
            ->create();

        $taskB = Task::factory()
            ->for($user)
            ->for($project)
            ->create();

        $comment = Comment::factory()
            ->for($user)
            ->for($taskA)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/tasks/{$taskB->id}/comments/{$comment->id}")
            ->assertNotFound();
    }
}
