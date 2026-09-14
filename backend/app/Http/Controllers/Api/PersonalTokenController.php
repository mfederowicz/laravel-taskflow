<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonalTokenRequest;
use App\Http\Resources\PersonalTokenResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->whereJsonContains('abilities', 'pat')
            ->latest()
            ->get();

        return response()->json([
            'data' => PersonalTokenResource::collection($tokens),
        ]);
    }

    public function store(StorePersonalTokenRequest $request): JsonResponse
    {
        $expiresAt = $request->validated('expires_at')
            ? Carbon::parse($request->validated('expires_at'))
            : null;

        $name = $request->validated('name');
        $secret = bin2hex(random_bytes(32));
        $plain = $name.'-'.$secret;

        $token = $request->user()->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $secret),
            'abilities' => ['pat'],
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'data' => [
                'token' => new PersonalTokenResource($token),
                'plain_token' => $plain,
            ],
        ], 201);
    }

    public function destroy(Request $request, PersonalAccessToken $token): Response
    {
        if (
            $token->tokenable_id !== $request->user()->id ||
            ! in_array('pat', $token->abilities ?? [])
        ) {
            abort(403);
        }

        $token->delete();

        return response()->noContent();
    }
}
