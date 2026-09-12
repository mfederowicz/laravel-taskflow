<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'payload' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Render a human-readable, English sentence describing the event.
     */
    public function message(): string
    {
        $actor = $this->user?->name;

        $text = match ($this->type) {
            ActivityType::ProjectCreated => 'created the project',
            ActivityType::ProjectUpdated => 'updated the project',
            ActivityType::ProjectArchived => 'archived the project',
            ActivityType::ProjectRestored => 'restored the project from the archive',

            ActivityType::MemberAdded => 'added '.($this->payload['user'] ?? 'a member')
                .' as '.($this->payload['role'] ?? 'member'),

            ActivityType::MemberRoleChanged => 'changed '.($this->payload['user'] ?? 'a member')
                ."'s role from ".($this->payload['from'] ?? 'unknown')
                .' to '.($this->payload['to'] ?? 'unknown'),

            ActivityType::MemberRemoved => 'removed '.($this->payload['user'] ?? 'a member')
                .' from the project',

            ActivityType::TaskCreated => "created task '".($this->payload['task'] ?? 'Untitled')."'",
            ActivityType::TaskUpdated => "updated task '".($this->payload['task'] ?? 'Untitled')."'",
            ActivityType::TaskDeleted => "deleted task '".($this->payload['task'] ?? 'Untitled')."'",

            ActivityType::TaskTransferred => "transferred task '".($this->payload['task'] ?? 'Untitled')
                ."' from ".($this->payload['from'] ?? 'unknown')
                .' to '.($this->payload['to'] ?? 'unknown'),

            ActivityType::CommentAdded => "added a comment on task '".($this->payload['task'] ?? 'Untitled')."'",
            ActivityType::CommentUpdated => "updated a comment on task '".($this->payload['task'] ?? 'Untitled')."'",
            ActivityType::CommentDeleted => "deleted a comment on task '".($this->payload['task'] ?? 'Untitled')."'",
        };

        return $actor ? "{$actor} {$text}" : ucfirst($text);
    }
}
