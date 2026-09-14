<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    /**
     * Determine whether the user can delete a tag.
     *
     * Tags are a shared vocabulary; only the platform manager may delete one
     * (deleting detaches it from every task).
     */
    public function destroy(User $user, Tag $tag): bool
    {
        return $user->isManager();
    }
}
