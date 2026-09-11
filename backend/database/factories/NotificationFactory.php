<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'task_due',
            'title' => 'Task due soon',
            'body' => fake()->sentence(),
            'task_id' => Task::factory(),
            'read_at' => null,
        ];
    }
}
