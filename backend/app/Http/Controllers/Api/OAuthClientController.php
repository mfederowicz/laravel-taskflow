<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOAuthClientRequest;
use App\Http\Resources\OAuthClientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

class OAuthClientController extends Controller
{
    /**
     * List the password-grant OAuth2 clients (no secrets are exposed).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage-oauth-clients');

        $clients = Passport::client()
            ->newQuery()
            ->where('provider', 'users')
            ->where('revoked', false)
            ->where('grant_types', 'like', '%"password"%')
            ->latest()
            ->get();

        return OAuthClientResource::collection($clients);
    }

    /**
     * Create a password-grant OAuth2 client for the `users` provider.
     *
     * The plain secret is returned only once, at creation time.
     */
    public function store(StoreOAuthClientRequest $request, ClientRepository $clients): JsonResponse
    {
        $this->authorize('manage-oauth-clients');

        $client = $clients->createPasswordGrantClient(
            $request->validated('name'),
            'users',
            confidential: true,
        );

        return response()->json([
            'data' => [
                'client_id' => $client->id,
                'client_secret' => $client->plainSecret,
                'name' => $client->name,
            ],
        ], 201);
    }

    /**
     * Delete a password-grant OAuth2 client.
     */
    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorize('manage-oauth-clients');

        $client->forceDelete();

        return response()->json(null, 204);
    }
}
