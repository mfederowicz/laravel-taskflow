<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where(function (Builder $query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereIn('id', function (Builder $sub) use ($userId) {
                        $sub->select('project_id')
                            ->from('project_members')
                            ->where('user_id', $userId)
                            ->whereIn('role', ['admin', 'editor']);
                    })
                    ->whereNull('archived_at');
            })],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,in_progress,completed'],
            'priority' => ['required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
        ];
    }
}
