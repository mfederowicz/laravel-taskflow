<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pin_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/projects/{$project->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.pinned', true);

        $this->assertDatabaseHas('project_pins', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_unpin_removes_the_pin(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->projectPins()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/projects/{$project->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.pinned', false);

        $this->assertDatabaseMissing('project_pins', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_unpin_without_a_pin_is_idempotent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/projects/{$project->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.pinned', false);
    }

    public function test_pin_is_personal_to_each_user(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create(['user_id' => $member->id, 'role' => 'editor']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/projects/{$project->id}/pin")
            ->assertJsonPath('data.pinned', true);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pinned', false);

        $this->postJson("/api/v1/projects/{$project->id}/pin")
            ->assertJsonPath('data.pinned', true);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/projects')
            ->assertJsonPath('data.0.pinned', true);
    }

    public function test_pinned_projects_float_to_the_top_of_the_list(): void
    {
        $user = User::factory()->create();

        $first = Project::factory()->for($user)->create();
        $second = Project::factory()->for($user)->create();
        $third = Project::factory()->for($user)->create();

        $user->projectPins()->create(['project_id' => $second->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.pinned', true)
            ->assertJsonPath('data.1.id', $third->id)
            ->assertJsonPath('data.2.id', $first->id);
    }

    public function test_user_cannot_pin_a_project_they_cannot_access(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/projects/{$project->id}/pin")
            ->assertForbidden();
    }

    public function test_org_member_can_pin_an_org_project(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $project = Project::factory()->for($owner)->create([
            'organization_id' => $organization->id,
        ]);

        $member = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/projects/{$project->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.pinned', true);

        $this->getJson('/api/v1/projects')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pinned', true);
    }

    public function test_project_pins_cascade_when_the_project_is_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->projectPins()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_pins', ['project_id' => $project->id]);
    }

    public function test_show_reports_the_callers_pin_state(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $project->members()->create(['user_id' => $member->id, 'role' => 'editor']);
        $owner->projectPins()->create(['project_id' => $project->id]);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.pinned', false);
    }
}
