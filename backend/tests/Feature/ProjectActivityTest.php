<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\ProjectMemberRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectActivityTest extends TestCase
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

    public function test_project_creation_is_recorded(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects', [
            'name' => 'Tracked Project',
        ])->assertCreated();

        $this->assertDatabaseHas('activities', [
            'project_id' => Project::first()->id,
            'user_id' => $user->id,
            'type' => ActivityType::ProjectCreated->value,
        ]);
    }

    public function test_project_update_is_recorded_with_changed_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Renamed Project',
        ])->assertOk();

        $activity = Activity::where('type', ActivityType::ProjectUpdated->value)->first();

        $this->assertNotNull($activity);
        $this->assertSame(['name'], $activity->payload['fields']);
    }

    public function test_archiving_and_restoring_are_recorded(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/projects/{$project->id}/archive")->assertOk();
        $this->postJson("/api/v1/projects/{$project->id}/restore")->assertOk();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'type' => ActivityType::ProjectArchived->value,
        ]);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'type' => ActivityType::ProjectRestored->value,
        ]);
    }

    public function test_member_added_is_recorded(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'editor',
        ])->assertCreated();

        $activity = Activity::where('type', ActivityType::MemberAdded->value)->first();

        $this->assertNotNull($activity);
        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'type' => ActivityType::MemberAdded->value,
        ]);
        $this->assertSame($newMember->name, $activity->payload['user']);
        $this->assertSame('editor', $activity->payload['role']);
    }

    public function test_member_role_change_is_recorded(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member, ProjectMemberRole::Admin);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/projects/{$project->id}/members/{$project->members()->first()->id}", [
            'role' => 'viewer',
        ])->assertOk();

        $activity = Activity::where('type', ActivityType::MemberRoleChanged->value)->first();

        $this->assertNotNull($activity);
        $this->assertSame('admin', $activity->payload['from']);
        $this->assertSame('viewer', $activity->payload['to']);
        $this->assertSame($member->name, $activity->payload['user']);
    }

    public function test_member_removed_is_recorded(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member, ProjectMemberRole::Viewer);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$project->members()->first()->id}")
            ->assertNoContent();

        $activity = Activity::where('type', ActivityType::MemberRemoved->value)->first();

        $this->assertNotNull($activity);
        $this->assertSame($member->name, $activity->payload['user']);
    }

    public function test_task_creation_is_recorded(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Tracked Task',
            'status' => 'pending',
            'priority' => 'medium',
        ])->assertCreated();

        $activity = Activity::where('type', ActivityType::TaskCreated->value)->first();

        $this->assertNotNull($activity);
        $this->assertSame('Tracked Task', $activity->payload['task']);
        $this->assertSame($project->id, $activity->project_id);
        $this->assertSame($response->json('data.id'), $activity->task_id);
    }

    public function test_task_update_is_recorded(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create(['title' => 'Update Me']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'status' => 'completed',
        ])->assertOk();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'type' => ActivityType::TaskUpdated->value,
        ]);
    }

    public function test_task_deletion_is_recorded_with_title_snapshot(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create(['title' => 'Doomed Task']);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $activity = Activity::where('type', ActivityType::TaskDeleted->value)->first();

        $this->assertNotNull($activity);
        $this->assertSame('Doomed Task', $activity->payload['task']);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_task_without_project_is_not_recorded(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Orphan Task', 'project_id' => null]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'in_progress'])->assertOk();
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_task_transfer_is_recorded(): void
    {
        $manager = User::factory()->manager()->create();
        $fromUser = User::factory()->create();
        $toUser = User::factory()->create();
        $project = Project::factory()->for($fromUser)->create();
        $task = Task::factory()->for($fromUser)->for($project)->create(['title' => 'Handover']);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/tasks/{$task->id}/transfer", [
            'to_user_id' => $toUser->id,
        ])->assertOk();

        $activity = Activity::where('type', ActivityType::TaskTransferred->value)->first();

        $this->assertNotNull($activity);
        $this->assertSame('Handover', $activity->payload['task']);
        $this->assertSame($fromUser->name, $activity->payload['from']);
        $this->assertSame($toUser->name, $activity->payload['to']);
    }

    public function test_comment_crud_is_recorded(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create();

        Sanctum::actingAs($user);

        $commentId = $this->postJson("/api/v1/tasks/{$task->id}/comments", [
            'body' => 'First thought',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/tasks/{$task->id}/comments/{$commentId}", [
            'body' => 'Second thought',
        ])->assertOk();

        $this->deleteJson("/api/v1/tasks/{$task->id}/comments/{$commentId}")->assertNoContent();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'type' => ActivityType::CommentAdded->value,
        ]);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'type' => ActivityType::CommentUpdated->value,
        ]);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'type' => ActivityType::CommentDeleted->value,
        ]);
    }

    public function test_owner_and_member_can_view_activity_feed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member, ProjectMemberRole::Viewer);

        Activity::factory()->for($project)->count(3)->create();

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_manager_can_view_any_activity_feed(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $project = Project::factory()->for($owner)->create();

        Activity::factory()->for($project)->create();

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_outsider_cannot_view_activity_feed(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertForbidden();
    }

    public function test_activity_feed_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertUnauthorized();
    }

    public function test_activity_feed_is_newest_first(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $old = Activity::factory()->for($project)->for($owner)->create([
            'created_at' => now()->subHours(2),
        ]);
        $middle = Activity::factory()->for($project)->for($owner)->create([
            'created_at' => now()->subHour(),
        ]);
        $new = Activity::factory()->for($project)->for($owner)->create([
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertOk()
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $old->id);
    }

    public function test_activity_resource_exposes_message(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $activity = Activity::factory()->for($project)->for($owner)->create([
            'type' => ActivityType::TaskCreated,
            'payload' => ['task' => 'Write tests'],
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/projects/{$project->id}/activities")
            ->assertOk()
            ->assertJsonPath('data.0.id', $activity->id)
            ->assertJsonPath('data.0.type', 'task_created')
            ->assertJsonPath('data.0.message', "{$owner->name} created task 'Write tests'")
            ->assertJsonPath('data.0.actor.name', $owner->name);
    }

    public function test_project_deletion_cascades_activities(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        Activity::factory()->for($project)->count(2)->create();

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();

        $this->assertDatabaseCount('activities', 0);
    }
}
