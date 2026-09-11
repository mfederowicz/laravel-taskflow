<?php

namespace App\Policies;

use App\Enums\ProjectMemberRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || $user->isManager()
            || $this->isProjectMember($user, $task);
    }

    /**
     * Determine whether the user can transfer the task to another owner.
     *
     * Only managers may transfer a task; managers can never inherit one, so
     * the manager-to-regular-user direction is the only legal move and the
     * validate-transfer rule lives in TransferTaskRequest.
     */
    public function transfer(User $user, Task $task): bool
    {
        return $user->isManager();
    }

    /**
     * Determine whether the user can comment on the task.
     *
     * The task owner always may comment; members of the task's project may
     * comment when they hold at least the editor role.
     */
    public function comment(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || $this->isAdminOrEditor($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || $user->isManager()
            || $this->isAdminOrEditor($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || $user->isManager()
            || $this->isProjectAdmin($user, $task);
    }

    private function isProjectMember(User $user, Task $task): bool
    {
        return $task->project?->hasMember($user) ?? false;
    }

    private function isProjectAdmin(User $user, Task $task): bool
    {
        return $this->projectRole($user, $task) === ProjectMemberRole::Admin;
    }

    private function isAdminOrEditor(User $user, Task $task): bool
    {
        return in_array(
            $this->projectRole($user, $task),
            [ProjectMemberRole::Admin, ProjectMemberRole::Editor],
            true
        );
    }

    private function projectRole(User $user, Task $task): ?ProjectMemberRole
    {
        return $task->project?->getMemberRole($user);
    }
}
