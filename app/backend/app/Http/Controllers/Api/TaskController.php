<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Task::query()
                ->with('user')
                ->latest()
                ->paginate(10),
        ]);
    }
}
