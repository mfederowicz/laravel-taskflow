<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject the Passport password grant for locked accounts.
 *
 * Passport's PasswordGrant only validates credentials and would otherwise
 * issue a token for a locked user (unlike the Sanctum/JWT login endpoints).
 * Applying the same rule at the token endpoint keeps the response generic
 * (`invalid_grant`) so it does not leak account status.
 */
class RejectLockedPassportLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('post')
            && $request->input('grant_type') === 'password'
            && ($username = $request->input('username'))
            && User::where('email', $username)->first()?->isLocked()
        ) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The user credentials were incorrect.',
                'message' => 'The user credentials were incorrect.',
            ], 401);
        }

        return $next($request);
    }
}
