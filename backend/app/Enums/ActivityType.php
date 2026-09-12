<?php

namespace App\Enums;

enum ActivityType: string
{
    case ProjectCreated = 'project_created';
    case ProjectUpdated = 'project_updated';
    case ProjectArchived = 'project_archived';
    case ProjectRestored = 'project_restored';

    case MemberAdded = 'member_added';
    case MemberRoleChanged = 'member_role_changed';
    case MemberRemoved = 'member_removed';

    case TaskCreated = 'task_created';
    case TaskUpdated = 'task_updated';
    case TaskDeleted = 'task_deleted';
    case TaskTransferred = 'task_transferred';

    case CommentAdded = 'comment_added';
    case CommentUpdated = 'comment_updated';
    case CommentDeleted = 'comment_deleted';
}
