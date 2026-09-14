<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TaskStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        $tasks = Task::query()->when(
            ! $user->isManager(),
            fn (Builder $query) => $query->visibleTo($user)
        );

        $total = (clone $tasks)->count();
        $pending = (clone $tasks)->where('status', 'pending')->count();
        $inProgress = (clone $tasks)->where('status', 'in_progress')->count();
        $completed = (clone $tasks)->where('status', 'completed')->count();
        $overdue = (clone $tasks)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed')
            ->count();

        return [
            Stat::make('Total tasks', $total),
            Stat::make('Pending', $pending)
                ->color('gray'),
            Stat::make('In progress', $inProgress)
                ->color('warning'),
            Stat::make('Completed', $completed)
                ->color('success'),
            Stat::make('Overdue', $overdue)
                ->color($overdue > 0 ? 'danger' : 'success'),
        ];
    }
}
