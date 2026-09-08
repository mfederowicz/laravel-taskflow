<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Tymon\JWTAuth\Facades\JWTAuth;

#[Group('Authentication')]
class AuthController extends Controller
{
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
    public function logout(Request $request): JsonResponse
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

        return response()->json(null, 204);
    }

    /**
     * Sign in with JWT.
     *
     * Issues a signed JWT bearer token (tymon/jwt-auth).
     */
    public function loginJwt(Request $request)
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
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Create a Passport OAuth2 client.
     *
     * Dev convenience endpoint that creates (or returns) a password-grant
     * client used with POST /oauth/token.
     */
    public function getPassportClient(): JsonResponse
    {
        $client = Client::factory()->create([
            'provider' => 'users',
            'grant_types' => ['password', 'refresh_token'],
        ]);

        return response()->json([
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);
    }
}
