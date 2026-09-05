<?php

namespace App\Http\Requests;

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
        return [
            'project_id' => ['sometimes', 'required', 'integer', Rule::exists('projects', 'id')
                ->where(
                    'user_id',
                    $this->user()->id
                )],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:pending,in_progress,completed'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
