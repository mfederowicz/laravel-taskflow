<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $request->user()
            ->projects()
            ->with('user')
            ->latest()
            ->paginate(10);

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create(
            $request->validated()
        );

        return response()->json([
            'data' => new ProjectResource($project->load('user')),
        ], 201);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->load('user'));
    }

    public function update(
        UpdateProjectRequest $request,
        Project $project
    ): ProjectResource {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project->load('user'));
    }

    public function destroy(
        Request $request,
        Project $project
    ): JsonResponse {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }
}
