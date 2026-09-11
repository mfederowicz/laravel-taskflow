<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TransferTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

#[Group('Tasks')]
class TaskController extends Controller
{
    /**
     * List tasks.
     *
     * Regular users see their own tasks; managers see every task (optionally
     * narrowed to one owner via `user_id`). Filterable by status, priority, a
     * free-text search over the title and description, and an inclusive due
     * date range (due_from / due_to); paginated 10 per page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tasks = Task::query()
            ->with(['user', 'project'])
            ->when(
                ! $user->isManager(),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->when(
                $user->isManager() && $this->isValidInt($request->input('user_id')),
                fn ($query) => $query->where('user_id', (int) $request->input('user_id'))
            )
            ->when(
                $request->status,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $request->priority,
                fn ($query, $priority) => $query->where('priority', $priority)
            )
            ->when(
                trim((string) $request->string('search')),
                fn ($query, $search) => $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->when(
                $request->due_from,
                fn ($query, $dueFrom) => $query->whereDate('due_date', '>=', $dueFrom)
            )
            ->when(
                $request->due_to,
                fn ($query, $dueTo) => $query->whereDate('due_date', '<=', $dueTo)
            )
            ->latest()
            ->paginate(10);

        return TaskResource::collection($tasks);
    }

    /**
     * Create a task inside one of the user's projects and record the initial
     * ownership ("created by") history entry.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validated();

        $project = $request->user()
            ->projects()
            ->findOrFail($validated['project_id']);

        $task = $project->tasks()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'due_date' => $validated['due_date'] ?? null,
        ]);

        $task->ownershipHistories()->create([
            'performed_by' => $request->user()->id,
            'from_user_id' => null,
            'to_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new TaskResource(
                $task->load(['user', 'project', 'ownershipHistories.performedBy',
                    'ownershipHistories.fromUser', 'ownershipHistories.toUser', ])
            ),
        ], 201);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $previousStatus = $task->status;
        $task->update($request->validated());

        if ($previousStatus !== 'completed' && $task->status === 'completed') {
            $task->notifications()
                ->whereIn('type', [
                    NotificationType::TaskDue->value,
                    NotificationType::TaskOverdue->value,
                ])
                ->delete();
        }

        return new TaskResource(
            $task->load(['user', 'project'])
        );
    }

    /**
     * Delete a task.
     */
    public function destroy(Task $task): Response
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }

    /**
     * View a single task, optionally with its ownership history
     * (`?with=history`).
     */
    public function show(Request $request, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        $task->load(['user', 'project']);

        if ($this->wantsHistory($request)) {
            $task->load(['ownershipHistories.performedBy',
                'ownershipHistories.fromUser', 'ownershipHistories.toUser', ]);
        }

        return new TaskResource($task);
    }

    /**
     * Transfer a task to another (regular) user.
     *
     * Managers only, and the receiving user must be a regular, active user
     * other than the current owner (see TransferTaskRequest). The ownership
     * change and its history entry are written atomically.
     */
    public function transfer(TransferTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('transfer', $task);

        $toUserId = $request->validated('to_user_id');
        $performer = $request->user();

        DB::transaction(function () use ($task, $toUserId, $performer, $request) {
            $fromUserId = $task->user_id;

            $task->update(['user_id' => $toUserId]);

            $task->ownershipHistories()->create([
                'performed_by' => $performer->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'note' => $request->validated('note'),
            ]);
        });

        return new TaskResource(
            $task->load(['user', 'project', 'ownershipHistories.performedBy',
                'ownershipHistories.fromUser', 'ownershipHistories.toUser', ])
        );
    }

    private function wantsHistory(Request $request): bool
    {
        return $request->filled('with')
            && in_array(
                'history',
                array_map('trim', explode(',', (string) $request->string('with')))
            );
    }

    private function isValidInt(mixed $value): bool
    {
        return is_numeric($value) && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
}
