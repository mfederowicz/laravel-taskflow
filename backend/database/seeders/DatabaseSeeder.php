<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password123'),
            ]
        );

        $project = $user->projects()->create([
            'name' => 'Demo Project',
            'description' => 'Example project for TaskFlow.',
        ]);

        $task = $user->tasks()->create([
            'project_id' => $project->id,
            'title' => 'Welcome to TaskFlow',
            'description' => 'This is an example task.',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $task->comments()->create([
            'user_id' => $user->id,
            'body' => 'Welcome to the demo project!',
        ]);
    }
}
