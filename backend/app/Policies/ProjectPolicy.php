<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Any authenticated user may access the project list. Ownership and
     * membership are enforced by the controller, which scopes the query to
     * the current user's projects (e.g. $user->projects()).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * The project owner, every explicit project member, and — for
     * organization-owned projects — every organization member may view it.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->hasAccess($user);
    }

    /**
     * Determine whether the user can create models.
     *
     * Any authenticated user may create a project. The project is always
     * attached to the current user by the controller
     * (e.g. $user->projects()->create(...)), so it is owned by them.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * The project owner and project admins may update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $project->effectiveRole($user) === 'admin'
            || $project->effectiveRole($user) === 'owner';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $project->effectiveRole($user) === 'owner';
    }

    /**
     * Determine whether the user can view the project member list.
     * The project owner and any project member may view the roster.
     */
    public function viewMembers(User $user, Project $project): bool
    {
        return $project->hasAccess($user);
    }

    /**
     * Determine whether the user can manage project members
     * (add, change roles, or remove members).
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $project->effectiveRole($user) === 'owner'
            || $project->effectiveRole($user) === 'admin';
    }

    /**
     * Determine whether the user can archive the model.
     *
     * Platform managers, the project owner, and project admins may
     * archive a project.
     */
    public function archive(User $user, Project $project): bool
    {
        return $user->isManager()
            || $project->effectiveRole($user) === 'owner'
            || $project->effectiveRole($user) === 'admin';
    }

    /**
     * Determine whether the user can restore the model from archive.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->archive($user, $project);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
