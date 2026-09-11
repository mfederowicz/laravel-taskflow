<?php

namespace App\Enums;

enum NotificationType: string
{
    case TaskDue = 'task_due';

    case TaskOverdue = 'task_overdue';

    public function label(): string
    {
        return match ($this) {
            self::TaskDue => 'Task due soon',
            self::TaskOverdue => 'Task overdue',
        };
    }
}
