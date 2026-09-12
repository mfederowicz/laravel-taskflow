<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\Export;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

#[Group('Projects')]
class ProjectController extends Controller
{
    /**
     * List the authenticated user's projects: those they own plus any
     * project they belong to as a member. Archived projects are hidden by
     * default; pass `?archived=1` to list only archived projects.
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
            ->when(
                $request->boolean('archived'),
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at')
            )
            ->latest()
            ->paginate(10);

        return ProjectResource::collection($projects);
    }

    /**
     * Export the user's projects (owned + joined) as a CSV or JSON file,
     * mirroring the index scope. Format is chosen via `?format=csv|json`
     * (default csv).
     */
    public function export(ExportRequest $request): Response
    {
        $format = $request->validated('format') ?? 'csv';
        $user = $request->user();

        $projects = Project::query()
            ->with(['user', 'members'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('members', function ($members) use ($user) {
                        $members->where('user_id', $user->id);
                    });
            })
            ->when(
                $request->boolean('archived'),
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at')
            )
            ->latest()
            ->get();

        if ($format === 'json') {
            return response()->json(
                ProjectResource::collection($projects)->resolve(),
                200,
                ['Content-Disposition' => 'attachment; filename="projects.json"']
            );
        }

        $rows = $projects->map(function (Project $project) use ($user) {
            $role = $project->user_id === $user->id
                ? 'owner'
                : ($project->members->firstWhere('user_id', $user->id)?->role?->value ?? 'viewer');

            return [
                $project->id,
                $project->name,
                $project->description,
                $project->user->name,
                $role,
                $project->created_at?->toDateTimeString(),
                $project->updated_at?->toDateTimeString(),
            ];
        });

        $csv = Export::csv([
            'id', 'name', 'description', 'owner', 'role', 'created_at', 'updated_at',
        ], $rows);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="projects.csv"');
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

    /**
     * Archive a project. Archived projects are hidden from the default
     * project index but remain accessible by id.
     */
    public function archive(Request $request, Project $project): ProjectResource
    {
        $this->authorize('archive', $project);

        $project->markArchived();

        return new ProjectResource($project->load(['user', 'members']));
    }

    /**
     * Restore an archived project to the active index.
     */
    public function restore(Request $request, Project $project): ProjectResource
    {
        $this->authorize('restore', $project);

        $project->markActive();

        return new ProjectResource($project->load(['user', 'members']));
    }
}
