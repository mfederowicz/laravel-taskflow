<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharedProjectTest extends TestCase
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

    public function test_viewer_member_can_view_shared_project(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_non_member_cannot_view_shared_project(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_owner_can_update_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Updated name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated name')
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_admin_member_can_update_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $admin, ProjectMemberRole::Admin);

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Admins can edit',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_editor_member_cannot_update_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Editors cannot edit',
        ])->assertForbidden();
    }

    public function test_project_index_includes_owned_and_shared_projects(): void
    {
        $member = User::factory()->create();
        $owned = Project::factory()->for($member)->create(['name' => 'Mine']);
        $owner = User::factory()->create();
        $shared = Project::factory()->for($owner)->create(['name' => 'With team']);
        $this->addMember($shared, $member, ProjectMemberRole::Editor);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Mine', 'role' => 'owner'])
            ->assertJsonFragment(['name' => 'With team', 'role' => 'editor']);
    }

    public function test_viewer_member_can_view_shared_task(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.shared', true);
    }

    public function test_non_member_cannot_view_shared_task(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/tasks/{$task->id}")
            ->assertForbidden();
    }

    public function test_task_index_includes_owned_and_shared_tasks(): void
    {
        $member = User::factory()->create();
        $ownProject = Project::factory()->for($member)->create();
        $ownTask = Task::factory()->for($member)->for($ownProject)->create(['title' => 'Own task']);

        $owner = User::factory()->create();
        $sharedProject = Project::factory()->for($owner)->create();
        $this->addMember($sharedProject, $member, ProjectMemberRole::Editor);
        $sharedTask = Task::factory()->for($owner)->for($sharedProject)->create(['title' => 'Shared task']);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Own task', 'shared' => false])
            ->assertJsonFragment(['title' => 'Shared task', 'shared' => true]);
    }

    public function test_editor_can_create_task_in_shared_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Collaboration task',
            'status' => 'pending',
            'priority' => 'medium',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $editor->id)
            ->assertJsonPath('data.shared', true);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $editor->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_viewer_cannot_create_task_in_shared_project(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);

        Sanctum::actingAs($viewer);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Must not create',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_editor_can_update_task_in_shared_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_viewer_cannot_update_task_in_shared_project(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($viewer);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'status' => 'in_progress',
        ])->assertForbidden();
    }

    public function test_admin_can_delete_task_in_shared_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $admin, ProjectMemberRole::Admin);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_editor_cannot_delete_task_in_shared_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($editor);

        $this->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertForbidden();
    }

    public function test_editor_can_comment_on_shared_task(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $editor, ProjectMemberRole::Editor);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/tasks/{$task->id}/comments", [
            'body' => 'Collaborating here.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $editor->id);
    }

    public function test_viewer_cannot_comment_on_shared_task(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $viewer, ProjectMemberRole::Viewer);
        $task = Task::factory()->for($owner)->for($project)->create();

        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/tasks/{$task->id}/comments", [
            'body' => 'Viewers stay quiet.',
        ])->assertForbidden();
    }

    public function test_member_can_view_comments_on_shared_task(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member, ProjectMemberRole::Viewer);
        $task = Task::factory()->for($owner)->for($project)->create();
        Comment::factory()->for($task)->for($owner)->create(['body' => 'For the team.']);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
