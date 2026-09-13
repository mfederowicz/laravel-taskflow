<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

#[Group('Organizations')]
class OrganizationController extends Controller
{
    /**
     * List the authenticated user's organizations: those they own plus any
     * organization they belong to as a member.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $organizations = Organization::query()
            ->with(['owner', 'members.user'])
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('members', function ($members) use ($user) {
                        $members->where('user_id', $user->id);
                    });
            })
            ->latest()
            ->paginate(10);

        return OrganizationResource::collection($organizations);
    }

    /**
     * Create an organization. The current user becomes its owner.
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create(
            array_merge($request->validated(), ['owner_id' => $request->user()->id])
        );

        return response()->json([
            'data' => new OrganizationResource($organization->load(['owner', 'members.user'])),
        ], 201);
    }

    /**
     * View a single organization.
     */
    public function show(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        return new OrganizationResource(
            $organization->load(['owner', 'members.user'])
        );
    }

    /**
     * Update an organization.
     */
    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization
    ): OrganizationResource {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return new OrganizationResource(
            $organization->load(['owner', 'members.user'])
        );
    }

    /**
     * Delete an organization.
     *
     * Only the owner may delete it. Organization memberships are removed with
     * it; its projects are kept and detached from the organization.
     */
    public function destroy(Request $request, Organization $organization): Response
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->noContent();
    }
}
