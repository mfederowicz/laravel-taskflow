<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TransferTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Export;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
        $tasks = $this->scopedTaskQuery($request)
            ->latest()
            ->paginate(10);

        return TaskResource::collection($tasks);
    }

    /**
     * Export tasks as a CSV or JSON file.
     *
     * The export respects the same visibility scope and filters as the task
     * index (no pagination), so the file mirrors exactly what the user can
     * currently see. Format is chosen via `?format=csv|json` (default csv).
     */
    public function export(ExportRequest $request): Response
    {
        $format = $request->validated('format') ?? 'csv';

        $tasks = $this->scopedTaskQuery($request)
            ->latest()
            ->get();

        if ($format === 'json') {
            return response()->json(
                TaskResource::collection($tasks)->resolve(),
                200,
                ['Content-Disposition' => 'attachment; filename="tasks.json"']
            );
        }

        $rows = $tasks->map(fn (Task $task) => [
            $task->id,
            $task->title,
            $task->description,
            $task->status,
            $task->priority,
            $task->due_date,
            $task->project?->name,
            $task->user->name,
            $task->tags->map(fn ($tag) => $tag->name)->implode(', '),
            $task->created_at?->toDateTimeString(),
            $task->updated_at?->toDateTimeString(),
        ]);

        $csv = Export::csv([
            'id', 'title', 'description', 'status', 'priority', 'due_date',
            'project', 'owner', 'tags', 'created_at', 'updated_at',
        ], $rows);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="tasks.csv"');
    }

    /**
     * Create a task inside one of the user's projects and record the initial
     * ownership ("created by") history entry.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validated();

        $project = $this->eligibleProjects($request->user())
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

        $task->tags()->sync($request->validated('tag_ids') ?? []);

        return response()->json([
            'data' => new TaskResource(
                $task->load(['user', 'project', 'tags', 'ownershipHistories.performedBy',
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

        if ($request->has('tag_ids')) {
            $task->tags()->sync($request->validated('tag_ids'));
        }

        if ($previousStatus !== 'completed' && $task->status === 'completed') {
            $task->notifications()
                ->whereIn('type', [
                    NotificationType::TaskDue->value,
                    NotificationType::TaskOverdue->value,
                ])
                ->delete();
        }

        return new TaskResource(
            $task->load(['user', 'project', 'tags'])
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

        $task->load(['user', 'project', 'tags']);

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
            $task->load(['user', 'project', 'tags', 'ownershipHistories.performedBy',
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

    /**
     * Build the user-scoped, filterable task query shared by the index and
     * the export. Regular users see their own tasks plus tasks in projects
     * they belong to; managers see every task (optionally one owner via
     * `user_id`).
     */
    private function scopedTaskQuery(Request $request): Builder
    {
        $user = $request->user();

        return Task::query()
            ->with(['user', 'project', 'tags'])
            ->when(
                ! $user->isManager(),
                fn ($query) => $query->where(function ($subQuery) use ($user) {
                    $subQuery->where('user_id', $user->id)
                        ->orWhereHas('project.members', function ($members) use ($user) {
                            $members->where('user_id', $user->id);
                        });
                })
            )
            ->when(
                $request->filled('project_id'),
                fn ($query) => $query->where(
                    'project_id',
                    $request->integer('project_id')
                )
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
                $this->isValidInt($request->input('tag_id')),
                fn ($query, $tagId) => $query->whereHas('tags', function ($tagQuery) use ($tagId) {
                    $tagQuery->where('tags.id', (int) $tagId);
                })
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
            );
    }

    private function isValidInt(mixed $value): bool
    {
        return is_numeric($value) && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Projects the user may create tasks in: their own projects plus any
     * project where they hold at least the editor member role.
     */
    private function eligibleProjects(User $user)
    {
        return Project::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('members', function ($members) use ($user) {
                        $members->where('user_id', $user->id)
                            ->whereIn('role', ['admin', 'editor']);
                    });
            });
    }
}
