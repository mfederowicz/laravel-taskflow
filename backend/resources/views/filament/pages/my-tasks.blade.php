<x-filament-panels::page>
    <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-ta-header flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <x-filament-tables::search-field
                wire-model="search"
                debounce="300ms"
                placeholder="Search by title..."
            />

            <div class="w-full sm:w-48">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full table-auto text-start">
                <thead class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <x-filament-tables::header-cell name="title">Title</x-filament-tables::header-cell>
                        <x-filament-tables::header-cell name="status">Status</x-filament-tables::header-cell>
                        <x-filament-tables::header-cell name="due_date">Due date</x-filament-tables::header-cell>
                        <x-filament-tables::header-cell name="actions"></x-filament-tables::header-cell>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->tasks as $task)
                        <x-filament-tables::row wire:key="task-{{ $task->id }}">
                            <td class="fi-ta-cell px-3 py-4 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                <span class="text-sm text-gray-950 dark:text-white">{{ $task->title }}</span>
                            </td>
                            <td class="fi-ta-cell px-3 py-4">
                                <x-filament::badge>{{ str($task->status)->headline() }}</x-filament::badge>
                            </td>
                            <td class="fi-ta-cell px-3 py-4">
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $task->due_date ? \Illuminate\Support\Carbon::parse($task->due_date)->format('Y-m-d') : '—' }}
                                </span>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-end sm:pe-6">
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    wire:click="toggleComplete({{ $task->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleComplete({{ $task->id }})"
                                >
                                    {{ $task->status === 'completed' ? 'Mark pending' : 'Mark done' }}
                                </x-filament::button>
                            </td>
                        </x-filament-tables::row>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No tasks found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->tasks->hasPages())
            <div class="fi-ta-footer px-4 py-3 sm:px-6">
                <x-filament::pagination :paginator="$this->tasks" />
            </div>
        @endif
    </div>
</x-filament-panels::page>
