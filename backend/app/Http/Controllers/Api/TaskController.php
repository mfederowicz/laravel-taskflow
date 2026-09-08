<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Tasks')]
class TaskController extends Controller
{
    /**
     * List the authenticated user's tasks.
     *
     * Filterable by status and priority; paginated 10 per page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tasks = $request->user()
            ->tasks()
            ->with(['user', 'project'])
            ->when(
                $request->status,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $request->priority,
                fn ($query, $priority) => $query->where('priority', $priority)
            )
            ->latest()
            ->paginate(10);

        return TaskResource::collection($tasks);
    }

    /**
     * Create a task inside one of the user's projects.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
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

        return response()->json([
            'data' => new TaskResource($task->load(['user', 'project'])),
        ], 201);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource(
            $task->load(['user', 'project'])
        );
    }

    /**
     * Delete a task.
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * View a single task.
     */
    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load(['user', 'project']));
    }
}
