<?php

namespace App\Policies;

use App\Models\OrganizationMember;
use App\Models\User;

class OrganizationMemberPolicy
{
    /**
     * Determine whether the user can update a member's role.
     */
    public function update(User $user, OrganizationMember $member): bool
    {
        return $member->organization->isMemberAdmin($user);
    }

    /**
     * Determine whether the user can remove a member from the organization.
     */
    public function delete(User $user, OrganizationMember $member): bool
    {
        return $member->organization->isMemberAdmin($user);
    }
}
