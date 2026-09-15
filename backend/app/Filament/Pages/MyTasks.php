<?php

namespace App\Filament\Pages;

use App\Models\Task;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class MyTasks extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Livewire';

    protected static string $view = 'filament.pages.my-tasks';

    public string $search = '';

    public string $status = '';

    public function getTitle(): string
    {
        return 'My Tasks';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function toggleComplete(int $taskId): void
    {
        $task = $this->baseQuery()->findOrFail($taskId);

        $task->update([
            'status' => $task->status === 'completed' ? 'pending' : 'completed',
        ]);
    }

    #[Computed]
    public function tasks()
    {
        return $this->baseQuery()
            ->when($this->search, fn (Builder $query) => $query->where('title', 'like', "%{$this->search}%"))
            ->when($this->status, fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('due_date')
            ->paginate(10);
    }

    protected function baseQuery(): Builder
    {
        $user = auth()->user();

        return Task::query()->when(
            ! $user->isManager(),
            fn (Builder $query) => $query->visibleTo($user)
        );
    }
}
