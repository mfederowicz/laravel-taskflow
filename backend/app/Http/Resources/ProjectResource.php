<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'role' => $this->effectiveRole($user),
            'archived' => $this->archived_at !== null,
            'pinned' => $this->whenLoaded('pins', fn () => $this->pins->isNotEmpty(), false),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
