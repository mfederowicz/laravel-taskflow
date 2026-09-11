<?php

namespace App\Http\Resources;

use App\Models\Tag;
use App\Models\TaskOwnershipHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'project' => $this->project
                ? [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                ]
                : null,
            'shared' => $this->project
                ? $this->project->user_id !== $request->user()?->id
                : false,
            'tags' => $this->tags->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ]),
            'ownership_history' => $this->whenLoaded('ownershipHistories', function () {
                return $this->ownershipHistories->map(
                    fn (TaskOwnershipHistory $entry) => [
                        'id' => $entry->id,
                        'performed_by' => $entry->performedBy?->id,
                        'performed_by_name' => $entry->performedBy?->name,
                        'from_user_id' => $entry->from_user_id,
                        'from_user_name' => $entry->fromUser?->name,
                        'to_user_id' => $entry->to_user_id,
                        'to_user_name' => $entry->toUser?->name,
                        'note' => $entry->note,
                        'created_at' => $entry->created_at,
                    ]
                );
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
