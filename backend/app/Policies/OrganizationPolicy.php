<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Any authenticated user may access the organization list. Ownership and
     * membership are enforced by the controller, which scopes the query to
     * the current user's organizations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * The organization owner and every organization member may view it.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $organization->isOwner($user) || $organization->hasMember($user);
    }

    /**
     * Determine whether the user can create models.
     *
     * Any authenticated user may create an organization. The organization is
     * always attached to the current user by the controller, so it is owned
     * by them.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * The organization owner and organization admins may update it.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $organization->isMemberAdmin($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $organization->isOwner($user);
    }

    /**
     * Determine whether the user can view the organization member list.
     *
     * The organization owner and any organization member may view the roster.
     */
    public function viewMembers(User $user, Organization $organization): bool
    {
        return $organization->isOwner($user) || $organization->hasMember($user);
    }

    /**
     * Determine whether the user can manage organization members
     * (add, change roles, or remove members).
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $organization->isMemberAdmin($user);
    }
}
