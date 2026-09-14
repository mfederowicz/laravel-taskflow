<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationMemberRequest;
use App\Http\Requests\UpdateOrganizationMemberRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Organization Members')]
class OrganizationMemberController extends Controller
{
    /**
     * List members of an organization.
     *
     * The owner and any organization member may view the roster.
     */
    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('viewMembers', $organization);

        return OrganizationMemberResource::collection(
            $organization->members()
                ->with('user')
                ->latest()
                ->paginate(25)
        );
    }

    /**
     * Add a member to an organization.
     *
     * Only the owner or an organization admin may add members. The user must
     * be an active account and must not already be a member of the
     * organization; the owner cannot be added as a member.
     */
    public function store(StoreOrganizationMemberRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $request->validated();

        try {
            $member = $organization->members()->create([
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
            ]);
        } catch (QueryException $e) {
            // The (organization_id, user_id) unique index guards against a
            // duplicate add slipping between validation and insert (two tabs /
            // concurrent admins). Surfaced as a validation error, not a 500.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'The user is already a member of this organization.',
                    'errors' => [
                        'user_id' => ['The user is already a member of this organization.'],
                    ],
                ], 422);
            }

            throw $e;
        }

        $member->load('user');

        return response()->json([
            'data' => new OrganizationMemberResource($member),
        ], 201);
    }

    /**
     * Change a member's role.
     *
     * Only the owner or an organization admin may change roles.
     */
    public function update(
        UpdateOrganizationMemberRequest $request,
        Organization $organization,
        OrganizationMember $member
    ): OrganizationMemberResource {
        $this->authorize('update', $member);

        $member->update($request->validated());
        $member->load('user');

        return new OrganizationMemberResource($member);
    }

    /**
     * Remove a member from an organization.
     *
     * Only the owner or an organization admin may remove members.
     */
    public function destroy(Request $request, Organization $organization, OrganizationMember $member): Response
    {
        $this->authorize('delete', $member);

        $member->delete();

        return response()->noContent();
    }
}
