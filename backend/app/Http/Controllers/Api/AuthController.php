<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Laravel\Sanctum\PersonalAccessToken;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

#[Group('Authentication')]
class AuthController extends Controller
{
    public const PASSPORT_CLIENT_NAME = 'TaskFlow auto client';

    /**
     * Create a new user account.
     *
     * Returns the created user plus a Sanctum personal access token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Sign in with Sanctum.
     *
     * Issues a Sanctum personal access token for the authenticated user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Refresh an expired JWT.
     *
     * Accepts an expired-but-within-window bearer token and returns a fresh
     * token with a new 60-minute TTL. The refresh window (default 7 days,
     * config `jwt.refresh_ttl`) is enforced by tymon/jwt-auth.
     */
    public function refreshJwt(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        try {
            $token = JWTAuth::setToken($token)->refresh();
        } catch (TokenExpiredException|TokenInvalidException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $user = auth('jwt')->setToken($token)->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Refresh an expired Sanctum token.
     *
     * Sanctum has no native refresh tokens; this endpoint rotates a still
     * present but expired personal access token into a fresh one, provided the
     * original was created within the configured refresh window (default 7
     * days, config `sanctum.refresh_expiration`). The old token is deleted.
     */
    public function refreshSanctum(Request $request): JsonResponse
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken($plainToken);

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $refreshWindow = (int) config('sanctum.refresh_expiration', 7);

        if ($accessToken->created_at->lt(now()->subDays($refreshWindow))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $user = $accessToken->tokenable;

        $accessToken->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Sign out the current user.
     *
     * Revokes the active token according to the selected authentication method
     * (Sanctum: delete token, JWT: blacklist, Passport: revoke token).
     */
    public function logout(Request $request): Response
    {
        $user = $request->user();

        $method = $request->header('X-Auth-Method', 'sanctum');

        switch ($method) {
            case 'jwt':
                auth('jwt')->logout();
                break;

            case 'passport':
                $user->currentAccessToken()?->revoke();
                break;

            default:
                $user->currentAccessToken()?->delete();
                break;
        }

        return response()->noContent();
    }

    /**
     * Sign in with JWT.
     *
     * Issues a signed JWT bearer token (tymon/jwt-auth).
     *
     * Response shape matches the Sanctum login for a consistent frontend.
     */
    public function loginJwt(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => JWTAuth::user(),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Create a Passport OAuth2 client.
     *
     * Dev convenience endpoint used with POST /oauth/token. Passport hashes
     * client secrets, so a fresh client is created per request; only clients
     * tagged by this endpoint are pruned, so manually created password-grant
     * clients (e.g. via passport:client) are preserved.
     */
    public function getPassportClient(): JsonResponse
    {
        Client::where('provider', 'users')
            ->where('name', self::PASSPORT_CLIENT_NAME)
            ->delete();

        $client = Client::factory()->create([
            'name' => self::PASSPORT_CLIENT_NAME,
            'provider' => 'users',
            'grant_types' => ['password', 'refresh_token'],
        ]);

        return response()->json([
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);
    }
}
