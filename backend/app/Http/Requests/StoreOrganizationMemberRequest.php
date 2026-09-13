<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->route('organization')->id;

        return [
            'user_id' => [
                'required',
                'integer',
                'not_in:'.(string) $this->route('organization')->owner_id,
                Rule::exists('users', 'id')
                    ->where('status', 'active'),
                Rule::unique('organization_members', 'user_id')
                    ->where('organization_id', $organizationId),
            ],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }
}
