<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Project;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Project Activities')]
class ActivityController extends Controller
{
    /**
     * List a project's activity feed, newest first.
     *
     * Visible to the project owner, any project member, and platform
     * managers (who may inspect any project).
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user->isManager()) {
            $this->authorize('view', $project);
        }

        return ActivityResource::collection(
            $project->activities()
                ->with('user')
                ->latest('created_at')
                ->paginate(25)
        );
    }
}
