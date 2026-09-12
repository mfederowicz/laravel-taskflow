<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(
        Project $project,
        User $user,
        ProjectMemberRole $role = ProjectMemberRole::Editor
    ): void {
        $project->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function test_owner_can_archive_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.archived', true);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
        ]);

        $this->assertNotNull($project->fresh()->archived_at);
    }

    public function test_archived_projects_are_hidden_from_default_index(): void
    {
        $user = User::factory()->create();

        $active = Project::factory()->for($user)->create();
        $archived = Project::factory()->for($user)->create([
            'archived_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['id' => $archived->id]);
    }

    public function test_archived_parameter_returns_only_archived_projects(): void
    {
        $user = User::factory()->create();

        $active = Project::factory()->for($user)->create();
        $archived = Project::factory()->for($user)->create([
            'archived_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects?archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->id)
            ->assertJsonPath('data.0.archived', true)
            ->assertJsonMissing(['name' => $active->name]);
    }

    public function test_admin_member_can_archive_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $admin, ProjectMemberRole::Admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.archived', true);
    }

    public function test_editor_member_cannot_archive_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);

        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertForbidden();

        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_viewer_member_cannot_archive_project(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);

        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertForbidden();
    }

    public function test_non_member_cannot_archive_project(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($outsider);

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertForbidden();
    }

    public function test_manager_can_archive_any_project(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.archived', true);
    }

    public function test_owner_can_restore_archived_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'archived_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/projects/{$project->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.archived', false);

        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_archived_project_still_viewable_by_id(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create([
            'archived_at' => now()->subDay(),
        ]);
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.archived', true);
    }

    public function test_tasks_of_archived_project_stay_visible(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'archived_at' => now()->subDay(),
        ]);

        $task = Task::factory()->for($user)->for($project)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks?project_id='.$project->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task->id);
    }

    public function test_cannot_create_task_in_archived_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create([
            'archived_at' => now()->subDay(),
        ]);

        $editor = User::factory()->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Task in archived project',
            'status' => 'pending',
            'priority' => 'medium',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Task in archived project',
        ]);
    }

    public function test_cannot_archive_project_when_unauthenticated(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertUnauthorized();
    }
}
