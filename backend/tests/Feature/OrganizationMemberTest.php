<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $member->id)
            ->assertJsonPath('data.0.role', 'editor');
    }

    public function test_member_can_list_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'viewer',
        ]);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_member_cannot_list_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertForbidden();
    }

    public function test_owner_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'editor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $newMember->id)
            ->assertJsonPath('data.role', 'editor');

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $newMember->id,
            'role' => 'editor',
        ]);
    }

    public function test_admin_member_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $newMember = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
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
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_viewer_member_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $newMember = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);

        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $member->id,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_organization_owner_as_member(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $owner->id,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_locked_user_as_member(): void
    {
        $owner = User::factory()->create();
        $locked = User::factory()->locked()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $locked->id,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_non_existent_user_as_member(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => 999999,
            'role' => 'editor',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $member->id,
            'role' => 'superadmin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_owner_can_update_member_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organizationMember = $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/v1/organizations/{$organization->id}/members/{$organizationMember->id}",
            ['role' => 'admin']
        )
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('organization_members', [
            'id' => $organizationMember->id,
            'role' => 'admin',
        ]);
    }

    public function test_editor_cannot_update_member_role(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $peer = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);
        $peerMember = $organization->members()->create([
            'user_id' => $peer->id,
            'role' => 'viewer',
        ]);

        Sanctum::actingAs($editor);

        $this->patchJson(
            "/api/v1/organizations/{$organization->id}/members/{$peerMember->id}",
            ['role' => 'admin']
        )->assertForbidden();
    }

    public function test_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organizationMember = $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/organizations/{$organization->id}/members/{$organizationMember->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('organization_members', [
            'id' => $organizationMember->id,
        ]);
    }

    public function test_viewer_cannot_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $peer = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organization->members()->create([
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);
        $peerMember = $organization->members()->create([
            'user_id' => $peer->id,
            'role' => 'editor',
        ]);

        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/v1/organizations/{$organization->id}/members/{$peerMember->id}")
            ->assertForbidden();
    }

    public function test_deleting_an_organization_removes_its_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organizationMember = $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        $organization->delete();

        $this->assertDatabaseMissing('organization_members', [
            'id' => $organizationMember->id,
        ]);
    }

    public function test_deleting_a_user_removes_their_memberships(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->for($owner, 'owner')->create();
        $organizationMember = $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        $member->delete();

        $this->assertDatabaseMissing('organization_members', [
            'id' => $organizationMember->id,
        ]);
    }
}
