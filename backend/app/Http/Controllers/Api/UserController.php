<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchUsersRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Passport\Token;

#[Group('Users')]
class UserController extends Controller
{
    /**
     * List all users (manager only).
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(User::latest()->paginate(10));
    }

    /**
     * Search active accounts (for inviting users to a project).
     *
     * Available to managers, project owners, and project admins. Returns a
     * small, unnested set of active users matching the query.
     */
    public function search(SearchUsersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('search', User::class);

        $query = User::query()
            ->where('status', UserStatus::Active)
            ->orderBy('name');

        if ($q = trim((string) $request->validated('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return UserResource::collection($query->limit(10)->get());
    }

    /**
     * Lock a user's account and revoke their active tokens.
     */
    public function lock(User $user): JsonResponse
    {
        $this->authorize('lock', $user);

        $user->update(['status' => UserStatus::Locked]);

        $user->tokens()->delete();
        Token::where('user_id', $user->id)->update(['revoked' => true]);

        return response()->json([
            'data' => new UserResource($user->refresh()),
        ]);
    }

    /**
     * Unlock a user's account.
     */
    public function unlock(User $user): JsonResponse
    {
        $this->authorize('unlock', $user);

        $user->update(['status' => UserStatus::Active]);

        return response()->json([
            'data' => new UserResource($user->refresh()),
        ]);
    }

    /**
     * Change a user's role (user/manager).
     */
    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('updateRole', $user);

        $user->update(['role' => $request->validated('role')]);

        return response()->json([
            'data' => new UserResource($user->refresh()),
        ]);
    }

    public function resetPassword(
        UpdateUserPasswordRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('resetPassword', $user);

        $user->update(['password' => $request->validated('password')]);

        return response()->json([
            'data' => new UserResource($user->refresh()),
        ]);
    }
}
