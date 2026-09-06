<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MultiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->header('X-Auth-Method', 'sanctum');

        if (! in_array($method, ['sanctum', 'jwt'], true)) {
            Log::warning('Invalid authentication method', [
                'auth_method' => $method,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $guard = auth()->guard($method);

        if (! $guard->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        auth()->shouldUse($method);

        return $next($request);
    }
}
