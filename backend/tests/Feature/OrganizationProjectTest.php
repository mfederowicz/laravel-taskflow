<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationProjectTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithProject(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $project = $organization->projects()->create([
            'user_id' => $owner->id,
            'name' => 'Org Project',
        ]);

        return [$owner, $organization, $project];
    }

    private function addMember(Organization $organization, User $user, string $role): void
    {
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function test_org_viewer_can_view_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $viewer = User::factory()->create();
        $this->addMember($organization, $viewer, 'viewer');

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer')
            ->assertJsonPath('data.organization.id', $organization->id);
    }

    public function test_org_viewer_cannot_update_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $viewer = User::factory()->create();
        $this->addMember($organization, $viewer, 'viewer');

        Sanctum::actingAs($viewer);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Hijacked',
        ])->assertForbidden();
    }

    public function test_org_editor_cannot_update_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $editor = User::factory()->create();
        $this->addMember($organization, $editor, 'editor');

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'description' => 'Blocked by org editor',
        ])->assertForbidden();
    }

    public function test_org_admin_can_update_and_delete_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, 'admin');

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'description' => 'Admin edit',
        ])->assertOk();

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();
    }

    public function test_explicit_project_member_role_overrides_org_role(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $user = User::factory()->create();
        $this->addMember($organization, $user, 'viewer');
        $project->members()->create([
            'user_id' => $user->id,
            'role' => ProjectMemberRole::Admin,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->putJson("/api/v1/projects/{$project->id}", [
            'description' => 'Promoted by explicit role',
        ])->assertOk();
    }

    public function test_outsider_cannot_view_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_org_projects_appear_in_the_members_project_index(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $member = User::factory()->create();
        $this->addMember($organization, $member, 'viewer');

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.role', 'viewer');
    }

    public function test_org_projects_appear_in_the_members_task_index(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'title' => 'Org task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $member = User::factory()->create();
        $this->addMember($organization, $member, 'viewer');

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task->id);
    }

    public function test_org_editor_can_create_a_task_in_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $editor = User::factory()->create();
        $this->addMember($organization, $editor, 'editor');

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'user_id' => $editor->id,
            'title' => 'New org task',
            'status' => 'pending',
            'priority' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('data.project.id', $project->id);
    }

    public function test_org_editor_can_be_assigned_a_task_in_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $editor = User::factory()->create();
        $this->addMember($organization, $editor, 'editor');

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'user_id' => $editor->id,
            'title' => 'Assigned to org editor',
            'status' => 'pending',
            'priority' => 'medium',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $editor->id);
    }

    public function test_org_viewer_cannot_be_assigned_a_task_in_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $viewer = User::factory()->create();
        $this->addMember($organization, $viewer, 'viewer');

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'title' => 'Must not go to a viewer',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_org_editor_can_reassign_a_task_to_another_org_editor(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $assignee = User::factory()->create();
        $editor = User::factory()->create();
        $this->addMember($organization, $assignee, 'editor');
        $this->addMember($organization, $editor, 'editor');

        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'title' => 'Org task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'user_id' => $assignee->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $assignee->id);
    }

    public function test_org_owner_can_update_a_task_in_an_org_project_they_do_not_own(): void
    {
        $orgOwner = User::factory()->create();
        $organization = Organization::factory()->for($orgOwner, 'owner')->create();
        $projectOwner = User::factory()->create();
        $project = $organization->projects()->create([
            'user_id' => $projectOwner->id,
            'name' => 'Org Project',
        ]);
        $assignee = User::factory()->create();
        $this->addMember($organization, $assignee, 'editor');

        $task = $project->tasks()->create([
            'user_id' => $projectOwner->id,
            'title' => 'Org task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($orgOwner);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'user_id' => $assignee->id,
            'title' => 'Adjusted by org owner',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $assignee->id)
            ->assertJsonPath('data.title', 'Adjusted by org owner');
    }

    public function test_org_editor_can_move_a_task_to_another_project_in_their_org(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $sibling = $organization->projects()->create([
            'user_id' => $owner->id,
            'name' => 'Sibling Project',
        ]);
        $editor = User::factory()->create();
        $this->addMember($organization, $editor, 'editor');

        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'title' => 'Move me',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'project_id' => $sibling->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.project.id', $sibling->id);
    }

    public function test_org_viewer_cannot_create_a_task_in_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $viewer = User::factory()->create();
        $this->addMember($organization, $viewer, 'viewer');

        Sanctum::actingAs($viewer);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'title' => 'Blocked task',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_org_owner_can_create_a_project_inside_the_org(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/projects', [
            'name' => 'New org project',
            'organization_id' => $organization->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization.id', $organization->id);

        $this->assertDatabaseHas('projects', [
            'name' => 'New org project',
            'organization_id' => $organization->id,
        ]);
    }

    public function test_org_admin_can_create_a_project_inside_the_org(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $this->addMember($organization, $admin, 'admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/projects', [
            'name' => 'Admin org project',
            'organization_id' => $organization->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization.id', $organization->id);
    }

    public function test_org_editor_cannot_create_a_project_inside_the_org(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $this->addMember($organization, $editor, 'editor');

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/projects', [
            'name' => 'Blocked org project',
            'organization_id' => $organization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_org_admin_can_manage_members_of_an_org_project(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $admin = User::factory()->create();
        $newMember = User::factory()->create();
        $this->addMember($organization, $admin, 'admin');

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_org_projects_do_not_appear_in_the_outsiders_project_index(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_organization_id_filter_returns_only_the_matching_orgs_projects(): void
    {
        [$owner, $orgA, $projectA] = $this->orgWithProject();
        $orgB = Organization::factory()->for($owner, 'owner')->create();
        $projectB = $orgB->projects()->create([
            'user_id' => $owner->id,
            'name' => 'Org B Project',
        ]);
        $member = User::factory()->create();
        $orgA->members()->create(['user_id' => $member->id, 'role' => 'admin']);
        $orgB->members()->create(['user_id' => $member->id, 'role' => 'admin']);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/projects?organization_id={$orgA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $projectA->id);
    }

    public function test_organization_id_filter_excludes_projects_outsider_cannot_access(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_owner_can_assign_their_personal_project_to_an_org(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Personal']);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertOk()
            ->assertJsonPath('data.organization.id', $organization->id);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_owner_can_detach_a_project_from_an_org(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.organization', null);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'organization_id' => null,
        ]);
    }

    public function test_org_admin_can_assign_a_personal_project_into_their_org(): void
    {
        $projectOwner = User::factory()->create();
        $orgAdmin = User::factory()->create();
        $organization = Organization::factory()->for($orgAdmin, 'owner')->create();
        $project = Project::factory()->for($projectOwner)->create(['name' => 'Personal']);

        Sanctum::actingAs($orgAdmin);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertOk()
            ->assertJsonPath('data.organization.id', $organization->id);
    }

    public function test_assigned_project_becomes_visible_to_org_members(): void
    {
        $projectOwner = User::factory()->create();
        $orgAdmin = User::factory()->create();
        $viewer = User::factory()->create();
        $organization = Organization::factory()->for($orgAdmin, 'owner')->create();
        $organization->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
        $project = Project::factory()->for($projectOwner)->create(['name' => 'Personal']);

        Sanctum::actingAs($orgAdmin);
        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertOk();

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_org_viewer_cannot_assign_a_project_into_the_org(): void
    {
        $projectOwner = User::factory()->create();
        $viewer = User::factory()->create();
        $organization = Organization::factory()->for($projectOwner, 'owner')->create();
        $organization->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
        $project = Project::factory()->for($projectOwner)->create(['name' => 'Personal']);

        Sanctum::actingAs($viewer);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_outsider_cannot_assign_a_project_into_someone_elses_org(): void
    {
        $projectOwner = User::factory()->create();
        $outsider = User::factory()->create();
        $organization = Organization::factory()->for($projectOwner, 'owner')->create();
        $project = Project::factory()->for($projectOwner)->create(['name' => 'Personal']);

        Sanctum::actingAs($outsider);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_non_owner_cannot_detach_a_project_from_an_org(): void
    {
        [$owner, $organization, $project] = $this->orgWithProject();
        $editor = User::factory()->create();
        $this->addMember($organization, $editor, 'editor');

        Sanctum::actingAs($editor);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => null,
        ])->assertForbidden();
    }

    public function test_owner_cannot_assign_a_project_to_an_org_they_do_not_manage(): void
    {
        $projectOwner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $organization = Organization::factory()->for($otherOwner, 'owner')->create();
        $project = Project::factory()->for($projectOwner)->create(['name' => 'Personal']);

        Sanctum::actingAs($projectOwner);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_org_owner_can_assign_a_personal_project_into_their_own_org(): void
    {
        $projectOwner = User::factory()->create();
        $orgOwner = User::factory()->create();
        $organization = Organization::factory()->for($orgOwner, 'owner')->create();
        $project = Project::factory()->for($projectOwner)->create(['name' => 'Personal']);

        Sanctum::actingAs($orgOwner);

        $this->patchJson("/api/v1/projects/{$project->id}/organization", [
            'organization_id' => $organization->id,
        ])->assertOk()
            ->assertJsonPath('data.organization.id', $organization->id);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_org_owner_is_an_admin_of_an_org_project_they_do_not_own(): void
    {
        $creator = User::factory()->create();
        $orgOwner = User::factory()->create();
        $organization = Organization::factory()->for($orgOwner, 'owner')->create();
        $project = $organization->projects()->create([
            'user_id' => $creator->id,
            'name' => 'Org Project',
        ]);

        Sanctum::actingAs($orgOwner);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->putJson("/api/v1/projects/{$project->id}", [
            'description' => 'Org owner edit',
        ])->assertOk();
    }

    public function test_org_projects_appear_in_the_org_owners_project_index(): void
    {
        $creator = User::factory()->create();
        $orgOwner = User::factory()->create();
        $organization = Organization::factory()->for($orgOwner, 'owner')->create();
        $project = $organization->projects()->create([
            'user_id' => $creator->id,
            'name' => 'Org Project',
        ]);

        Sanctum::actingAs($orgOwner);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.role', 'admin');
    }

    public function test_org_projects_tasks_appear_in_the_org_owners_task_index(): void
    {
        $creator = User::factory()->create();
        $orgOwner = User::factory()->create();
        $organization = Organization::factory()->for($orgOwner, 'owner')->create();
        $project = $organization->projects()->create([
            'user_id' => $creator->id,
            'name' => 'Org Project',
        ]);
        $task = $project->tasks()->create([
            'user_id' => $creator->id,
            'title' => 'Org task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($orgOwner);

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task->id);
    }

    public function test_org_owner_can_create_a_task_in_an_org_project_they_do_not_own(): void
    {
        $creator = User::factory()->create();
        $orgOwner = User::factory()->create();
        $organization = Organization::factory()->for($orgOwner, 'owner')->create();
        $project = $organization->projects()->create([
            'user_id' => $creator->id,
            'name' => 'Org Project',
        ]);

        Sanctum::actingAs($orgOwner);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'user_id' => $orgOwner->id,
            'title' => 'New org task',
            'status' => 'pending',
            'priority' => 'medium',
        ])
            ->assertCreated()
            ->assertJsonPath('data.project.id', $project->id);
    }
}
