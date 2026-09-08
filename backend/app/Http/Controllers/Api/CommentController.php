<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Comments')]
class CommentController extends Controller
{
    /**
     * List comments for a task.
     */
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $comments = $task->comments()
            ->with('user')
            ->latest()
            ->paginate(10);

        return CommentResource::collection($comments);
    }

    /**
     * Add a comment to a task.
     */
    public function store(
        StoreCommentRequest $request,
        Task $task
    ): JsonResponse {
        $this->authorize('comment', $task);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated()['body'],
        ]);

        return response()->json([
            'data' => new CommentResource(
                $comment->load('user')
            ),
        ], 201);
    }

    /**
     * View a single comment.
     */
    public function show(Request $request, Task $task, Comment $comment): CommentResource
    {
        $this->authorize('view', $comment);

        return new CommentResource($comment->load('user'));
    }

    /**
     * Update a comment.
     */
    public function update(
        UpdateCommentRequest $request,
        Task $task,
        Comment $comment
    ): CommentResource {
        $this->authorize('update', $comment);

        $comment->update($request->validated());

        return new CommentResource($comment->load('user'));
    }

    /**
     * Delete a comment.
     */
    public function destroy(Request $request, Task $task, Comment $comment): Response
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
