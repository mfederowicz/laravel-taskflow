<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'frequency',
    ];

    public function isRecurring(): bool
    {
        return in_array($this->frequency, ['daily', 'weekly', 'monthly', 'yearly'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function ownershipHistories(): HasMany
    {
        return $this->hasMany(TaskOwnershipHistory::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Scope a query to the tasks a given user may see: their own tasks, or
     * tasks belonging to a project they have access to (direct membership,
     * organization membership, or organization ownership). Managers are
     * left unscoped by the caller since they may see every task.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $subQuery) use ($user) {
            $subQuery->where('user_id', $user->id)
                ->orWhereHas('project.members', function ($members) use ($user) {
                    $members->where('user_id', $user->id);
                })
                ->orWhereHas('project.organization.members', function ($members) use ($user) {
                    $members->where('user_id', $user->id);
                })
                ->orWhereHas('project.organization', function ($organization) use ($user) {
                    $organization->where('owner_id', $user->id);
                });
        });
    }
}
