<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $member->id)
            ->assertJsonPath('data.0.role', 'editor');
    }

    public function test_member_can_list_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Viewer,
        ]);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_member_cannot_list_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$project->id}/members")
            ->assertForbidden();
    }

    public function test_owner_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'editor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $newMember->id)
            ->assertJsonPath('data.role', 'editor');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $newMember->id,
            'role' => 'editor',
        ]);
    }

    public function test_admin_member_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $newMember = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $admin->id,
            'role' => ProjectMemberRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_editor_member_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $newMember = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $editor->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_viewer_member_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $newMember = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $viewer->id,
            'role' => ProjectMemberRole::Viewer,
        ]);

        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $member->id,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_project_owner_as_member(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $owner->id,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_locked_user_as_member(): void
    {
        $owner = User::factory()->create();
        $locked = User::factory()->locked()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $locked->id,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_non_existent_user_as_member(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => 999999,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $member->id,
            'role' => 'superadmin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_owner_can_update_member_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $projectMember = $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/v1/projects/{$project->id}/members/{$projectMember->id}",
            ['role' => 'admin']
        )
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('project_members', [
            'id' => $projectMember->id,
            'role' => 'admin',
        ]);
    }

    public function test_editor_cannot_update_member_role(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $peer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $editor->id,
            'role' => ProjectMemberRole::Editor,
        ]);
        $peerMember = $project->members()->create([
            'user_id' => $peer->id,
            'role' => ProjectMemberRole::Viewer,
        ]);

        Sanctum::actingAs($editor);

        $this->patchJson(
            "/api/v1/projects/{$project->id}/members/{$peerMember->id}",
            ['role' => 'admin']
        )->assertForbidden();
    }

    public function test_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $projectMember = $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$projectMember->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_members', [
            'id' => $projectMember->id,
        ]);
    }

    public function test_viewer_cannot_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $peer = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create([
            'user_id' => $viewer->id,
            'role' => ProjectMemberRole::Viewer,
        ]);
        $peerMember = $project->members()->create([
            'user_id' => $peer->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$peerMember->id}")
            ->assertForbidden();
    }

    public function test_deleting_a_project_removes_its_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $projectMember = $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        $project->delete();

        $this->assertDatabaseMissing('project_members', [
            'id' => $projectMember->id,
        ]);
    }

    public function test_deleting_a_user_removes_their_memberships(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $projectMember = $project->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor,
        ]);

        $member->delete();

        $this->assertDatabaseMissing('project_members', [
            'id' => $projectMember->id,
        ]);
    }
}
