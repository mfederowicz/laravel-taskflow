<?php

namespace App\Http\Requests;

use App\Enums\ProjectMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project')->id;

        return [
            'user_id' => [
                'required',
                'integer',
                'not_in:'.(string) $this->route('project')->user_id,
                Rule::exists('users', 'id')
                    ->where('status', 'active'),
                Rule::unique('project_members', 'user_id')
                    ->where('project_id', $projectId),
            ],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ];
    }
}
