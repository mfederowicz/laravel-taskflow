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

        $role = $this->user_id === $user?->id
            ? 'owner'
            : ($this->members?->firstWhere('user_id', $user?->id)?->role->value ?? 'viewer');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'role' => $role,
            'archived' => $this->archived_at !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
