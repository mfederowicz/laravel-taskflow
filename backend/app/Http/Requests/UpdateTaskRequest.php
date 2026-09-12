<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'project_id' => ['sometimes', 'required', 'integer', Rule::exists('projects', 'id')->where(function (Builder $query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereIn('id', function (Builder $sub) use ($userId) {
                        $sub->select('project_id')
                            ->from('project_members')
                            ->where('user_id', $userId)
                            ->whereIn('role', ['admin', 'editor']);
                    })
                    ->whereNull('archived_at');
            })],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:pending,in_progress,completed'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
        ];
    }
}
