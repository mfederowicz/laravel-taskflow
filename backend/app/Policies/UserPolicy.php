<?php

namespace App\Policies;

use App\Enums\ProjectMemberRole;
use App\Models\ProjectMember;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the user list.
     *
     * Only managers administer accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    /**
     * Determine whether the user can search users (to invite them to a
     * project). Managers, project owners, and project admins may search.
     */
    public function search(User $user): bool
    {
        if ($user->isManager()) {
            return true;
        }

        if ($user->projects()->exists()) {
            return true;
        }

        return ProjectMember::query()
            ->where('user_id', $user->id)
            ->where('role', ProjectMemberRole::Admin)
            ->exists();
    }

    /**
     * Determine whether the user can view the model.
     *
     * Managers may view anyone; regular users only themselves.
     */
    public function view(User $user, User $target): bool
    {
        return $user->isManager() || $user->id === $target->id;
    }

    /**
     * Determine whether the user can lock the model.
     *
     * A manager cannot lock themselves (prevents admin lockout).
     */
    public function lock(User $user, User $target): bool
    {
        return $user->isManager() && $user->id !== $target->id;
    }

    /**
     * Determine whether the user can unlock the model.
     */
    public function unlock(User $user, User $target): bool
    {
        return $user->isManager();
    }

    /**
     * Determine whether the user can change the model's role.
     *
     * A manager cannot change their own role (prevents admin loss).
     */
    public function updateRole(User $user, User $target): bool
    {
        return $user->isManager() && $user->id !== $target->id;
    }

    /**
     * Determine whether the user can reset the model's password.
     */
    public function resetPassword(User $user, User $target): bool
    {
        return $user->isManager();
    }
}
