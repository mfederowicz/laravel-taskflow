<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_due_soon_notification_for_task_within_lead_window(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $task = Task::factory()->for($user)->create([
            'status' => 'pending',
            'due_date' => $today,
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'type' => 'task_due',
            'read_at' => null,
        ]);
    }

    public function test_command_creates_overdue_notification(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()->for($user)->create([
            'status' => 'in_progress',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'type' => 'task_overdue',
        ]);
    }

    public function test_command_skips_tasks_without_due_date_or_far_in_the_future(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create([
            'status' => 'pending',
            'due_date' => null,
        ]);
        Task::factory()->for($user)->create([
            'status' => 'pending',
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_skips_completed_tasks(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create([
            'status' => 'completed',
            'due_date' => now()->toDateString(),
        ]);
        Task::factory()->for($user)->create([
            'status' => 'completed',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_is_idempotent(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create([
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();
        $this->artisan('notifications:send-due')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_command_skips_locked_owners(): void
    {
        $locked = User::factory()->locked()->create();

        Task::factory()->for($locked)->create([
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_completing_a_task_clears_its_reminders(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create([
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'status' => 'completed',
        ])->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_user_can_list_and_count_unread_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->for($user)->create(['type' => 'task_due']);
        Notification::factory()->for($user)->create(['type' => 'task_overdue', 'read_at' => now()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'task_overdue');

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 1);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create(['type' => 'task_due']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', now()->toDateTimeString());

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->for($user)->count(3)->create(['type' => 'task_due']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 3);

        $this->assertDatabaseMissing('notifications', ['read_at' => null]);
    }

    public function test_user_cannot_read_or_modify_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::factory()->for($owner)->create(['type' => 'task_due']);

        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden();
    }

    public function test_deleting_a_task_cascades_its_notifications(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create([
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $this->artisan('notifications:send-due')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);

        $task->delete();

        $this->assertDatabaseCount('notifications', 0);
    }
}
