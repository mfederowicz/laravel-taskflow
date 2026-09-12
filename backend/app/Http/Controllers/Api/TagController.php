<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Tags')]
class TagController extends Controller
{
    /**
     * List every tag (shared vocabulary used for task filtering and labels).
     */
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::query()->orderBy('name')->get());
    }

    /**
     * Create a tag.
     *
     * Tags are a shared vocabulary — any active user may create one; the
     * name must be unique.
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::create($request->validated());

        return response()->json([
            'data' => new TagResource($tag),
        ], 201);
    }

    /**
     * Delete a tag and detach it from every task.
     */
    public function destroy(Tag $tag): Response
    {
        $tag->delete();

        return response()->noContent();
    }
}
