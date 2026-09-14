<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function getMemberRole(User $user): ?OrganizationRole
    {
        if ($this->relationLoaded('members')) {
            $member = $this->members->firstWhere('user_id', $user->id);

            return $member?->role;
        }

        $member = $this->members()
            ->where('user_id', $user->id)
            ->first();

        return $member?->role;
    }

    public function isMemberAdmin(User $user): bool
    {
        return $this->isOwner($user)
            || $this->getMemberRole($user) === OrganizationRole::Admin;
    }
}
