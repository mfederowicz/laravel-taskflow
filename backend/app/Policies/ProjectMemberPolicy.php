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
        return $member->project->effectiveRole($user) === 'owner'
            || $member->project->effectiveRole($user) === 'admin';
    }

    /**
     * Determine whether the user can remove a member from the project.
     */
    public function delete(User $user, ProjectMember $member): bool
    {
        return $member->project->effectiveRole($user) === 'owner'
            || $member->project->effectiveRole($user) === 'admin';
    }
}
