<?php

namespace App\Policies;

use App\Enums\ProjectMemberRole;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view the comment.
     *
     * The author, the task owner, and any member of the task's project may
     * view a comment.
     */
    public function view(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id
            || $user->id === $comment->task->user_id
            || (bool) ($comment->task->project?->hasMember($user) ?? false);
    }

    /**
     * Determine whether the user can update the comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can delete the comment.
     *
     * The author, the task owner, or a project admin of the task's project
     * may delete a comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id
            || $user->id === $comment->task->user_id
            || $comment->task->project?->getMemberRole($user) === ProjectMemberRole::Admin;
    }
}
