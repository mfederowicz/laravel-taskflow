<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_tags(): void
    {
        $this->getJson('/api/v1/tags')
            ->assertStatus(401);
    }

    public function test_user_can_list_tags(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'bug']);
        Tag::factory()->create(['name' => 'feature']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'bug')
            ->assertJsonPath('data.1.name', 'feature');
    }

    public function test_user_can_create_tag(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tags', [
            'name' => 'urgent',
            'color' => '#ff0000',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'urgent')
            ->assertJsonPath('data.color', '#ff0000');

        $this->assertDatabaseHas('tags', ['name' => 'urgent']);
    }

    public function test_tag_name_must_be_unique_and_color_valid(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'urgent']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tags', ['name' => 'urgent'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/tags', [
            'name' => 'okay',
            'color' => 'not-a-hex',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('color');
    }

    public function test_user_can_delete_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'temporary']);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/tags/{$tag->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_deleting_a_tag_detaches_it_from_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->create();
        $task = Task::factory()->for($user)->for($project)->create();
        $task->tags()->attach($tag);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/tags/{$tag->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('tag_task', ['tag_id' => $tag->id]);
    }

    public function test_user_can_create_task_with_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->create(['name' => 'bug']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Fix the thing',
            'status' => 'pending',
            'priority' => 'high',
            'tag_ids' => [$tag->id],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.tags.0.name', 'bug');

        $this->assertDatabaseHas('tag_task', [
            'task_id' => Task::first()->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_invalid_tag_ids_are_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Bad tag',
            'status' => 'pending',
            'priority' => 'low',
            'tag_ids' => [9999],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tag_ids.0');
    }

    public function test_user_can_update_task_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->for($project)->create();
        $first = Tag::factory()->create(['name' => 'first']);
        $second = Tag::factory()->create(['name' => 'second']);
        $task->tags()->attach($first);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'tag_ids' => [$second->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.tags.0.name', 'second');

        $this->assertDatabaseHas('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $second->id,
        ]);
        $this->assertDatabaseMissing('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $first->id,
        ]);
    }

    public function test_tasks_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $bug = Tag::factory()->create(['name' => 'bug']);
        $feature = Tag::factory()->create(['name' => 'feature']);

        $bugTask = Task::factory()->for($user)->for($project)->create(['title' => 'Broken thing']);
        $bugTask->tags()->attach($bug);
        $confidenceTask = Task::factory()->for($user)->for($project)->create(['title' => 'Nice thing']);
        $confidenceTask->tags()->attach($feature);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tasks?tag_id='.$bug->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Broken thing');
    }

    public function test_tag_column_is_included_in_csv_export(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->create(['name' => 'highlight']);
        $task = Task::factory()->for($user)->for($project)->create(['title' => 'Tagged export']);
        $task->tags()->attach($tag);

        Sanctum::actingAs($user);

        $this->get('/api/v1/tasks/export')
            ->assertOk()
            ->assertSee('highlight');
    }
}
