<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => null,
            'user_id' => User::factory(),
            'type' => ActivityType::TaskCreated,
            'payload' => ['task' => fake()->sentence(3)],
            'created_at' => now(),
        ];
    }
}
