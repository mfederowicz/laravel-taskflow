<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $comments = $task->comments()
            ->with('user')
            ->latest()
            ->paginate(10);

        return CommentResource::collection($comments);
    }

    public function store(
        StoreCommentRequest $request,
        Task $task
    ): CommentResource {
        $this->authorize('view', $task);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated()['body'],
        ]);

        return new CommentResource(
            $comment->load('user')
        );
    }
}
