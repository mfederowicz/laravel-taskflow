<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
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
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
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
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

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
    public function loginJwt(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

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
