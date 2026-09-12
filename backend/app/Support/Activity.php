<?php

namespace App\Support;

use App\Enums\ActivityType;
use App\Models\Activity as ActivityModel;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class Activity
{
    /**
     * Record an event in a project's activity feed.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        Project $project,
        ActivityType $type,
        ?User $actor = null,
        array $payload = [],
        ?Task $task = null
    ): ActivityModel {
        return $project->activities()->create([
            'user_id' => $actor?->id,
            'task_id' => $task?->id,
            'type' => $type,
            'payload' => $payload ?: null,
        ]);
    }
}
