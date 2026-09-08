<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_projects(): void
    {
        $user = User::factory()->create();

        Project::factory()
            ->count(3)
            ->for($user)
            ->create();

        Project::factory()
            ->count(2)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Test Project',
            'description' => 'Test description',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Project')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Test Project',
        ]);
    }

    public function test_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = Project::factory()
            ->for($owner)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_user_can_update_their_project(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Updated Project',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Project');
    }

    public function test_user_can_delete_their_project(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_user_cannot_assign_task_to_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = Project::factory()
            ->for($owner)
            ->create();

        Sanctum::actingAs($otherUser);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Unauthorized task',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertNotFound();
    }

    public function test_project_creation_requires_a_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }
}
