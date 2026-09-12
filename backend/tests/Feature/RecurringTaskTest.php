<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_task_with_frequency(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Weekly report',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => '2026-09-01',
            'frequency' => 'weekly',
        ])
            ->assertCreated()
            ->assertJsonPath('data.frequency', 'weekly');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Weekly report',
            'frequency' => 'weekly',
        ]);
    }

    public function test_completing_a_recurring_task_creates_the_next_occurrence(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'title' => 'Standup notes',
            'description' => 'Daily summary',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => '2026-09-01',
            'frequency' => 'daily',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Standup notes',
            'description' => 'Daily summary',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => '2026-09-02',
            'frequency' => 'daily',
        ]);
    }

    public function test_recurring_clone_copies_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'frequency' => 'weekly',
        ]);
        $task->tags()->attach($tag);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $clone = Task::where('status', 'pending')
            ->where('due_date', '2026-09-08')
            ->firstOrFail();

        $this->assertSame([$tag->id], $clone->tags()->pluck('tags.id')->all());
    }

    public function test_the_series_recurs_on_the_clones_completion(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'frequency' => 'monthly',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $clone = Task::where('frequency', 'monthly')
            ->where('due_date', '2026-10-01')
            ->firstOrFail();

        $this->putJson("/api/v1/tasks/{$clone->id}", ['status' => 'completed'])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'frequency' => 'monthly',
            'due_date' => '2026-11-01',
        ]);
    }

    public function test_completing_a_non_recurring_task_does_not_clone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'frequency' => null,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_updating_a_recurring_task_without_completing_it_does_not_clone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'frequency' => 'daily',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_manager_completing_a_recurring_task_keeps_the_original_owner(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $task = Task::factory()->for($owner)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'frequency' => 'weekly',
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $clone = Task::where('frequency', 'weekly')
            ->where('due_date', '2026-09-08')
            ->firstOrFail();

        $this->assertSame($owner->id, $clone->user_id);
    }

    public function test_recurring_clone_records_ownership_history_and_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'frequency' => 'daily',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $clone = Task::where('status', 'pending')
            ->where('due_date', '2026-09-02')
            ->firstOrFail();

        $this->assertDatabaseHas('task_ownership_histories', [
            'task_id' => $clone->id,
            'performed_by' => $user->id,
            'to_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'task_id' => $clone->id,
            'type' => ActivityType::TaskCreated->value,
        ]);
    }

    public function test_frequency_without_due_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'No date',
            'status' => 'pending',
            'priority' => 'low',
            'frequency' => 'daily',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_invalid_frequency_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Bad cadence',
            'status' => 'pending',
            'priority' => 'low',
            'due_date' => '2026-09-01',
            'frequency' => 'hourly',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['frequency']);
    }

    public function test_setting_frequency_on_an_existing_status_update_is_rejected_without_due_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => null,
            'frequency' => null,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'due_date' => null,
            'frequency' => 'daily',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_yearly_frequency_advances_by_one_year(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create([
            'status' => 'pending',
            'due_date' => '2025-02-28',
            'frequency' => 'yearly',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'frequency' => 'yearly',
            'due_date' => '2026-02-28',
        ]);
    }
}
