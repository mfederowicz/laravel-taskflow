<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'expires_at' => $this->expires_at,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
        ];
    }
}
