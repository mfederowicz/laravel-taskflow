<?php

namespace App\Policies;

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
            || $this->projectRole($user, $task) !== null;
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
            || $this->isAdminOrOwner($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || $user->isManager()
            || $this->isAdminOrOwner($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || $user->isManager()
            || $this->isProjectAdminOrOwner($user, $task);
    }

    private function isProjectAdminOrOwner(User $user, Task $task): bool
    {
        return in_array(
            $this->projectRole($user, $task),
            ['owner', 'admin'],
            true
        );
    }

    private function isAdminOrOwner(User $user, Task $task): bool
    {
        return in_array(
            $this->projectRole($user, $task),
            ['owner', 'admin', 'editor'],
            true
        );
    }

    /**
     * The user's effective role in the task's project (owner, explicit
     * member, or organization member), or null when the task is not attached
     * to a project or the user has no access to it.
     */
    private function projectRole(User $user, Task $task): ?string
    {
        return $task->project?->effectiveRole($user);
    }
}
