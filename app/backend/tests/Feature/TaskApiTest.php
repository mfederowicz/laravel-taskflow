<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_tasks(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        Task::factory()
            ->count(3)
            ->for($user)
            ->for($project)
            ->create();

        Task::factory()
            ->count(2)
            ->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tasks');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_a_task(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $project = Project::factory()
            ->for($user)
            ->create();

        $response = $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Test task',
            'description' => 'Test description',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => '2026-09-05',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Test task')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Test task',
        ]);
    }

    public function test_user_can_view_their_task(): void
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

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
    }

    public function test_user_cannot_view_another_users_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = Task::factory()
            ->for($owner)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertForbidden();
    }

    public function test_user_can_update_their_task(): void
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

        $this->putJson("/api/tasks/{$task->id}", [
            'status' => 'completed',
            'priority' => 'high',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_user_can_delete_their_task(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_user_cannot_create_task_without_required_fields(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'status',
                'priority',
            ]);
    }

    public function test_user_cannot_create_task_with_invalid_status_or_priority(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', [
            'title' => 'Invalid task',
            'status' => 'invalid',
            'priority' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'priority',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $this->getJson('/api/tasks')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_can_create_task_in_their_project(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Project task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.project.name', $project->name);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Project task',
        ]);
    }
}
