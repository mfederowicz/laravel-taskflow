<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tasks = Task::query()
            ->with('user')
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
}
