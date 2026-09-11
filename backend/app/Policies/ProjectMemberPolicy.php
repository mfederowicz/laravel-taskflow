<?php

namespace App\Policies;

use App\Models\ProjectMember;
use App\Models\User;

class ProjectMemberPolicy
{
    /**
     * Determine whether the user can update a member's role.
     */
    public function update(User $user, ProjectMember $member): bool
    {
        return $user->id === $member->project->user_id
            || $member->project->isMemberAdmin($user);
    }

    /**
     * Determine whether the user can remove a member from the project.
     */
    public function delete(User $user, ProjectMember $member): bool
    {
        return $user->id === $member->project->user_id
            || $member->project->isMemberAdmin($user);
    }
}
