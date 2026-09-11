<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Project Members')]
class ProjectMemberController extends Controller
{
    /**
     * List members of a project.
     *
     * The owner and any project member may view the roster.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewMembers', $project);

        return ProjectMemberResource::collection(
            $project->members()
                ->with('user')
                ->latest()
                ->paginate(25)
        );
    }

    /**
     * Add a member to a project.
     *
     * Only the owner or a project admin may add members. The user must be
     * an active account and must not already be a member of the project.
     */
    public function store(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validated();

        $member = $project->members()->create([
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
        ]);

        return response()->json([
            'data' => new ProjectMemberResource($member->load('user')),
        ], 201);
    }

    /**
     * Change a member's role.
     *
     * Only the owner or a project admin may change roles.
     */
    public function update(
        UpdateProjectMemberRequest $request,
        Project $project,
        ProjectMember $member
    ): ProjectMemberResource {
        $this->authorize('update', $member);

        $member->update($request->validated());

        return new ProjectMemberResource($member->load('user'));
    }

    /**
     * Remove a member from a project.
     *
     * Only the owner or a project admin may remove members.
     */
    public function destroy(Request $request, Project $project, ProjectMember $member): Response
    {
        $this->authorize('delete', $member);

        $member->delete();

        return response()->noContent();
    }
}
