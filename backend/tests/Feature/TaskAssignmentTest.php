<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
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

    private function createProjectWithMembers(): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->addMember($project, $editor, ProjectMemberRole::Editor);
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);

        return [$owner, $editor, $viewer, $project];
    }

    public function test_user_can_create_task_assigned_to_another_project_member(): void
    {
        [$owner, $editor, $viewer, $project] = $this->createProjectWithMembers();

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Assigned task',
            'status' => 'pending',
            'priority' => 'medium',
            'user_id' => $viewer->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.user.id', $viewer->id);

        $taskId = $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->json('data.0.id');

        $this->assertDatabaseHas('task_ownership_histories', [
            'task_id' => $taskId,
            'performed_by' => $editor->id,
            'from_user_id' => null,
            'to_user_id' => $viewer->id,
        ]);
    }

    public function test_task_can_be_assigned_to_the_project_owner(): void
    {
        [$owner, $editor, , $project] = $this->createProjectWithMembers();

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Owner assigned',
            'status' => 'pending',
            'priority' => 'medium',
            'user_id' => $owner->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.user.id', $owner->id);
    }

    public function test_create_assignee_defaults_to_current_user(): void
    {
        [$owner, $editor, , $project] = $this->createProjectWithMembers();

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Self assigned',
            'status' => 'pending',
            'priority' => 'medium',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.user.id', $editor->id);
    }

    public function test_create_rejects_assignee_who_is_not_a_project_member(): void
    {
        [$owner, , , $project] = $this->createProjectWithMembers();
        $outsider = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Bad assignee',
            'status' => 'pending',
            'priority' => 'medium',
            'user_id' => $outsider->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_create_rejects_locked_assignee(): void
    {
        $locked = User::factory()->locked()->create();

        [$owner, , , $project] = $this->createProjectWithMembers();
        $this->addMember($project, $locked, ProjectMemberRole::Viewer);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Locked assignee',
            'status' => 'pending',
            'priority' => 'medium',
            'user_id' => $locked->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_editor_can_reassign_task_to_another_project_member(): void
    {
        [$owner, $editor, $viewer, $project] = $this->createProjectWithMembers();

        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'title' => 'Reassign me',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'user_id' => $viewer->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $viewer->id);

        $this->assertDatabaseHas('task_ownership_histories', [
            'task_id' => $task->id,
            'performed_by' => $editor->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $viewer->id,
        ]);
    }

    public function test_update_rejects_assignee_who_is_not_a_project_member(): void
    {
        [$owner, $editor, , $project] = $this->createProjectWithMembers();
        $outsider = User::factory()->create();

        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'title' => 'Stay',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'user_id' => $outsider->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_update_without_user_id_keeps_the_assignee(): void
    {
        [$owner, $editor, , $project] = $this->createProjectWithMembers();

        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'title' => 'Keep me',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Renamed',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $owner->id)
            ->assertJsonPath('data.title', 'Renamed');
    }
}
