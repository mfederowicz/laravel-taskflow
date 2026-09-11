<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;
use Illuminate\Console\Command;

class SendDueNotifications extends Command
{
    protected $signature = 'notifications:send-due';

    protected $description = 'Create in-app reminders for tasks due within the lead window or already overdue';

    public function handle(): int
    {
        $leadHours = (int) config('reminders.lead_hours', 24);

        $today = now()->toDateString();
        $leadDate = now()->addHours($leadHours)->toDateString();

        $dueSoonTasks = Task::query()
            ->with('user')
            ->whereNotNull('due_date')
            ->whereNot('status', 'completed')
            ->whereBetween('due_date', [$today, $leadDate])
            ->get();

        foreach ($dueSoonTasks as $task) {
            $this->createOnce($task, NotificationType::TaskDue, "Task '{$task->title}' is due on {$task->due_date}.");
        }

        $overdueTasks = Task::query()
            ->with('user')
            ->whereNotNull('due_date')
            ->whereNot('status', 'completed')
            ->where('due_date', '<', $today)
            ->get();

        foreach ($overdueTasks as $task) {
            $this->createOnce($task, NotificationType::TaskOverdue, "Task '{$task->title}' was due on {$task->due_date}.");
        }

        $this->info('Notifications generated successfully.');

        return Command::SUCCESS;
    }

    private function createOnce(Task $task, NotificationType $type, string $body): void
    {
        $owner = $task->user;

        if ($owner === null || ! $owner->isActive()) {
            return;
        }

        $exists = Notification::where('task_id', $task->id)
            ->where('type', $type)
            ->exists();

        if ($exists) {
            return;
        }

        Notification::create([
            'user_id' => $owner->id,
            'type' => $type,
            'title' => $type->label(),
            'body' => $body,
            'task_id' => $task->id,
        ]);
    }
}
