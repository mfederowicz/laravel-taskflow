<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_search_users(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/users/search?q=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Example');
    }

    public function test_project_owner_can_search_users(): void
    {
        $owner = User::factory()->create();
        Project::factory()->for($owner)->create();
        User::factory()->create(['name' => 'Bob Builder']);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/users/search?q=bob')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bob Builder');
    }

    public function test_project_admin_member_can_search_users(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        ProjectMember::factory()->for($project)->for($admin)->create(['role' => 'admin']);

        $target = User::factory()->create(['email' => 'carol@example.com']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users/search?q=carol')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', $target->email);
    }

    public function test_user_without_projects_cannot_search_users(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/search')
            ->assertForbidden();
    }

    public function test_search_excludes_locked_users(): void
    {
        $owner = User::factory()->create();
        Project::factory()->for($owner)->create();
        User::factory()->create(['name' => 'Dave Active']);
        User::factory()->locked()->create(['name' => 'Dave Locked']);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/users/search?q=dave')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dave Active');
    }

    public function test_search_without_query_returns_recent_active_users(): void
    {
        $owner = User::factory()->create();
        Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/users/search')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
