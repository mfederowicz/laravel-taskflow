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

        $response = $this->getJson('/api/v1/tasks');

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

        $response = $this->postJson('/api/v1/tasks', [
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

        $this->getJson("/api/v1/tasks/{$task->id}")
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

        $this->getJson("/api/v1/tasks/{$task->id}")
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

        $this->putJson("/api/v1/tasks/{$task->id}", [
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

        $this->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_user_cannot_create_task_without_required_fields(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', [])
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

        $this->postJson('/api/v1/tasks', [
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
        $this->getJson('/api/v1/tasks')
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

        $response = $this->postJson('/api/v1/tasks', [
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

    public function test_user_cannot_move_task_to_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $taskProject = Project::factory()
            ->for($otherUser)
            ->create();

        $userProject = Project::factory()
            ->for($owner)
            ->create();

        $task = Task::factory()
            ->for($owner)
            ->for($userProject)
            ->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'project_id' => $taskProject->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'project_id',
            ]);
    }

    public function test_user_cannot_update_another_users_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = Task::factory()
            ->for($owner)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'status' => 'completed',
        ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => $task->status,
        ]);
    }

    public function test_user_cannot_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = Task::factory()
            ->for($owner)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_user_can_search_tasks_by_title(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create(['title' => 'Deploy the dashboard']);
        Task::factory()->for($user)->create(['title' => 'Buy groceries']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks?search=dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Deploy the dashboard']);
    }

    public function test_user_can_search_tasks_by_description(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create([
            'title' => 'Misc task',
            'description' => 'Contains the keyword magnet',
        ]);
        Task::factory()->for($user)->create([
            'title' => 'Other task',
            'description' => 'No match here',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks?search=magnet')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Misc task']);
    }

    public function test_search_only_returns_own_tasks(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Task::factory()->for($otherUser)->create(['title' => 'Private weapon project']);
        Task::factory()->for($user)->create(['title' => 'Public weapon briefing']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks?search=weapon')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Public weapon briefing']);
    }

    public function test_user_can_filter_tasks_by_due_date_range(): void
    {
        $user = User::factory()->create();

        $within = Task::factory()->for($user)->create([
            'title' => 'Inside range',
            'due_date' => '2026-06-15',
        ]);
        Task::factory()->for($user)->create([
            'title' => 'Before range',
            'due_date' => '2026-06-01',
        ]);
        Task::factory()->for($user)->create([
            'title' => 'After range',
            'due_date' => '2026-07-01',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks?due_from=2026-06-10&due_to=2026-06-20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Inside range']);
    }

    public function test_task_filters_combine_search_due_date_and_status(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create([
            'title' => 'Launch Spring campaign',
            'status' => 'in_progress',
            'due_date' => '2026-09-15',
        ]);
        Task::factory()->for($user)->create([
            'title' => 'Spring cleanup',
            'status' => 'pending',
            'due_date' => '2026-09-15',
        ]);
        Task::factory()->for($user)->create([
            'title' => 'Launch Summer campaign',
            'status' => 'completed',
            'due_date' => '2026-09-15',
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/tasks?search=campaign&due_from=2026-09-01&due_to=2026-09-30&status=in_progress'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Launch Spring campaign']);
    }
}
