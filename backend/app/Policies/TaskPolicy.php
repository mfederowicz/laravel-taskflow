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
        return $user->id === $task->user_id || $user->isManager();
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
     * Only the task owner may add a comment — the same ownership rule used
     * by the other TaskPolicy methods.
     */
    public function comment(User $user, Task $task): bool
    {
        return $user->id === $task->user_id;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->id === $task->user_id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->id === $task->user_id;
    }
}
