<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_their_organizations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        Organization::factory()->for(User::factory(), 'owner')->create();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $organization->id)
            ->assertJsonPath('data.0.role', 'owner');
    }

    public function test_member_sees_organizations_they_belong_to(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'viewer',
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'viewer');
    }

    public function test_user_can_create_an_organization(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
            'description' => 'Engineering org',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Inc.')
            ->assertJsonPath('data.owner.id', $user->id)
            ->assertJsonPath('data.role', 'owner');

        $this->assertDatabaseHas('organizations', [
            'owner_id' => $user->id,
            'name' => 'Acme Inc.',
        ]);
    }

    public function test_organization_creation_requires_a_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_non_member_cannot_view_an_organization(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/organizations/{$organization->id}")
            ->assertForbidden();
    }

    public function test_owner_can_update_their_organization(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/organizations/{$organization->id}", [
            'name' => 'Renamed Org',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Org');
    }

    public function test_admin_member_can_update_the_organization(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/organizations/{$organization->id}", [
            'description' => 'Updated by admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated by admin');
    }

    public function test_editor_member_cannot_update_the_organization(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/organizations/{$organization->id}", [
            'name' => 'Hijacked',
        ])->assertForbidden();
    }

    public function test_owner_can_delete_their_organization(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'viewer',
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/organizations/{$organization->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('organizations', [
            'id' => $organization->id,
        ]);
        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $organization->id,
        ]);
    }

    public function test_admin_member_cannot_delete_the_organization(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/organizations/{$organization->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
        ]);
    }

    public function test_deleting_an_organization_keeps_its_projects(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $project = $organization->projects()->create([
            'user_id' => $owner->id,
            'name' => 'Org Project',
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/organizations/{$organization->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('organizations', [
            'id' => $organization->id,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'organization_id' => null,
        ]);
    }

    public function test_deleting_a_user_removes_their_organizations(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        $owner->delete();

        $this->assertDatabaseMissing('organizations', [
            'id' => $organization->id,
        ]);
    }
}
