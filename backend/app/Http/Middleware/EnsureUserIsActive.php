<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject authenticated but locked accounts on every protected endpoint.
     *
     * Works together with token revocation at lock time: Sanctum/Passport
     * tokens are revoked immediately, while the JWT guard has no server-side
     * revocation — this middleware makes a locked JWT session unusable for its
     * remaining validity, and the refresh endpoints block rotation entirely.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is locked.',
            ], 403);
        }

        return $next($request);
    }
}
