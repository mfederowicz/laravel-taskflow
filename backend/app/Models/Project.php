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
        'organization_id',
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function markArchived(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }

    public function markActive(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
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

    /**
     * Resolve the user's effective access level to the project.
     *
     * Precedence: the project owner, then an explicit project member role,
     * then the user's organization membership when the project belongs to an
     * organization — the organization owner is treated as an admin — otherwise
     * null (no access).
     *
     * @return 'owner'|'admin'|'editor'|'viewer'|null
     */
    public function effectiveRole(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        if ($this->getMemberRole($user) === null && $this->organization_id !== null) {
            return $this->organization?->getMemberRole($user)?->value
                ?? ($this->organization?->isOwner($user) ? 'admin' : null);
        }

        return $this->getMemberRole($user)?->value;
    }

    public function hasAccess(User $user): bool
    {
        return $this->effectiveRole($user) !== null;
    }
}
