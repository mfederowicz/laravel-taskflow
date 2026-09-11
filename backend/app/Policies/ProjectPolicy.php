<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Any authenticated user may access the project list. Ownership is
     * enforced by the controller, which scopes the query to the current
     * user's projects (e.g. $user->projects()).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * The project owner and every project member may view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->user_id || $project->hasMember($user);
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
        return $user->id === $project->user_id || $project->isMemberAdmin($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Determine whether the user can view the project member list.
     * The project owner and any project member may view the roster.
     */
    public function viewMembers(User $user, Project $project): bool
    {
        return $user->id === $project->user_id || $project->hasMember($user);
    }

    /**
     * Determine whether the user can manage project members
     * (add, change roles, or remove members).
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->id === $project->user_id || $project->isMemberAdmin($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
