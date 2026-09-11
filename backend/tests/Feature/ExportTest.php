<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_export_own_tasks_as_csv(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['title' => 'CSV task']);

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/tasks/export');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="tasks.csv"')
            ->assertSee('CSV task');
    }

    public function test_user_can_export_tasks_as_json(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['title' => 'JSON task']);

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/tasks/export?format=json');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Content-Disposition', 'attachment; filename="tasks.json"')
            ->assertJsonPath('data.0.title', 'JSON task');
    }

    public function test_csv_export_respects_filters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->for($project)->create(['title' => 'Pending task', 'status' => 'pending']);
        Task::factory()->for($user)->for($project)->create(['title' => 'Completed task', 'status' => 'completed']);

        Sanctum::actingAs($user);

        $this->get('/api/v1/tasks/export?status=pending')
            ->assertOk()
            ->assertSee('Pending task')
            ->assertDontSee('Completed task');
    }

    public function test_user_export_excludes_other_users_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Task::factory()->for($user)->create(['title' => 'My task']);
        Task::factory()->for($other)->create(['title' => 'Other task']);

        Sanctum::actingAs($user);

        $this->get('/api/v1/tasks/export')
            ->assertOk()
            ->assertSee('My task')
            ->assertDontSee('Other task');
    }

    public function test_manager_export_includes_all_tasks(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create();
        Task::factory()->for($other)->create(['title' => 'Their task']);

        Sanctum::actingAs($manager);

        $this->get('/api/v1/tasks/export')
            ->assertOk()
            ->assertSee('Their task');
    }

    public function test_projects_csv_export_includes_owned_and_joined_projects(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Project::factory()->for($member)->create(['name' => 'Mine']);
        $shared = Project::factory()->for($owner)->create(['name' => 'Shared project']);
        $shared->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor->value,
        ]);

        Sanctum::actingAs($member);

        $this->get('/api/v1/projects/export')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="projects.csv"')
            ->assertSee('Mine')
            ->assertSee('Shared project');
    }

    public function test_projects_json_export_includes_member_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Project::factory()->for($member)->create(['name' => 'Mine']);
        $shared = Project::factory()->for($owner)->create(['name' => 'With team']);
        $shared->members()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::Editor->value,
        ]);

        Sanctum::actingAs($member);

        $this->get('/api/v1/projects/export?format=json')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="projects.json"')
            ->assertJsonFragment(['name' => 'With team', 'role' => 'editor'])
            ->assertJsonFragment(['name' => 'Mine', 'role' => 'owner']);
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/v1/tasks/export')->assertUnauthorized();

        $this->getJson('/api/v1/projects/export')->assertUnauthorized();
    }

    public function test_invalid_format_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks/export?format=xml')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }
}
