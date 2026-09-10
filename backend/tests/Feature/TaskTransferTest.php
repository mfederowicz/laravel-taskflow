<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_transfer_any_task_to_a_regular_user(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $receiver = User::factory()->create();

        $task = Task::factory()->for($owner)->create(['title' => 'Hand-off me']);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $receiver->id,
            'note' => 'Sprint hand-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $receiver->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'user_id' => $receiver->id,
        ]);

        $this->assertDatabaseHas('task_ownership_histories', [
            'task_id' => $task->id,
            'performed_by' => $manager->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $receiver->id,
            'note' => 'Sprint hand-off',
        ]);
    }

    public function test_transfer_keeps_comments_with_the_task(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $receiver = User::factory()->create();

        $task = Task::factory()->for($owner)->create();
        $comment = $task->comments()->create([
            'user_id' => $owner->id,
            'body' => 'Still in progress.',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $receiver->id,
        ])->assertOk();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'task_id' => $task->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_regular_user_cannot_transfer_a_task(): void
    {
        $owner = User::factory()->create();
        $receiver = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $receiver->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_transfer_to_a_manager_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $otherManager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $otherManager->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to_user_id']);
    }

    public function test_transfer_to_a_non_existent_user_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to_user_id']);
    }

    public function test_transfer_to_a_locked_user_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $locked = User::factory()->locked()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $locked->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to_user_id']);
    }

    public function test_transfer_to_the_current_owner_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $owner->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to_user_id']);
    }

    public function test_manager_index_lists_all_tasks_and_can_filter_by_owner(): void
    {
        $manager = User::factory()->manager()->create();
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();

        Task::factory()->count(2)->for($firstOwner)->create();
        Task::factory()->count(3)->for($secondOwner)->create();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->getJson('/api/v1/tasks?user_id='.$secondOwner->id)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.user.id', $secondOwner->id);
    }

    public function test_task_creation_records_initial_ownership_history_entry(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Brand new task',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertCreated();

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('task_ownership_histories', [
            'task_id' => $taskId,
            'performed_by' => $user->id,
            'from_user_id' => null,
            'to_user_id' => $user->id,
        ]);
    }

    public function test_show_includes_ownership_history_when_requested(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->create();
        $receiver = User::factory()->create();

        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $taskId = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'History traced',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$taskId}/transfer", [
            'to_user_id' => $receiver->id,
        ])->assertOk();

        $response = $this->getJson("/api/v1/tasks/{$taskId}?with=history")
            ->assertOk();

        $history = $response->json('data.ownership_history');
        $this->assertCount(2, $history);

        $this->assertNull($history[0]['from_user_id']);
        $this->assertSame($owner->id, $history[0]['to_user_id']);

        $this->assertSame($owner->id, $history[1]['from_user_id']);
        $this->assertSame($receiver->id, $history[1]['to_user_id']);
        $this->assertSame($manager->id, $history[1]['performed_by']);
    }

    public function test_show_without_with_parameter_omits_history(): void
    {
        $owner = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonMissingPath('ownership_history');
    }
}
