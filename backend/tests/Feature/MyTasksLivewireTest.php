<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Filament\Pages\MyTasks;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyTasksLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/filament/my-tasks')->assertRedirect('/filament/login');
    }

    public function test_authenticated_user_can_load_the_page(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        Task::factory()->for($user)->create(['title' => 'Mine']);

        $this->actingAs($user)
            ->get('/filament/my-tasks')
            ->assertOk()
            ->assertSee('Mine');
    }

    public function test_user_sees_only_their_own_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->for($user)->create(['title' => 'Mine']);
        Task::factory()->for($other)->create(['title' => 'Not mine']);

        $this->actingAs($user);

        Livewire::test(MyTasks::class)
            ->assertSee('Mine')
            ->assertDontSee('Not mine');
    }

    public function test_search_filters_by_title(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create(['title' => 'Write report']);
        Task::factory()->for($user)->create(['title' => 'Buy groceries']);

        $this->actingAs($user);

        Livewire::test(MyTasks::class)
            ->set('search', 'report')
            ->assertSee('Write report')
            ->assertDontSee('Buy groceries');
    }

    public function test_toggle_complete_flips_status(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['status' => 'pending']);

        $this->actingAs($user);

        Livewire::test(MyTasks::class)
            ->call('toggleComplete', $task->id);

        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_user_cannot_toggle_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($other)->create(['status' => 'pending']);

        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(MyTasks::class)
            ->call('toggleComplete', $task->id);
    }
}
