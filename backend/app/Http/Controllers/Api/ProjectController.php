<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Projects')]
class ProjectController extends Controller
{
    /**
     * List the authenticated user's projects: those they own plus any
     * project they belong to as a member.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $projects = Project::query()
            ->with(['user', 'members'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('members', function ($members) use ($user) {
                        $members->where('user_id', $user->id);
                    });
            })
            ->latest()
            ->paginate(10);

        return ProjectResource::collection($projects);
    }

    /**
     * Create a project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create(
            $request->validated()
        );

        return response()->json([
            'data' => new ProjectResource($project->load(['user', 'members'])),
        ], 201);
    }

    /**
     * View a single project.
     */
    public function show(Request $request, Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->load(['user', 'members']));
    }

    /**
     * Update a project.
     */
    public function update(
        UpdateProjectRequest $request,
        Project $project
    ): ProjectResource {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project->load(['user', 'members']));
    }

    /**
     * Delete a project.
     */
    public function destroy(
        Request $request,
        Project $project
    ): Response {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }
}
