<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement([
                'pending',
                'in_progress',
                'completed',
            ]),
            'priority' => fake()->randomElement([
                'low',
                'medium',
                'high',
            ]),
            'due_date' => fake()->optional()->date(),
        ];
    }
}
