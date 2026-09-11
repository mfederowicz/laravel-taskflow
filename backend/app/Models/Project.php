<?php

namespace App\Models;

use App\Enums\ProjectMemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function getMemberRole(User $user): ?ProjectMemberRole
    {
        $member = $this->members()
            ->where('user_id', $user->id)
            ->first();

        return $member?->role;
    }

    public function isMemberAdmin(User $user): bool
    {
        return $this->getMemberRole($user) === ProjectMemberRole::Admin;
    }
}
