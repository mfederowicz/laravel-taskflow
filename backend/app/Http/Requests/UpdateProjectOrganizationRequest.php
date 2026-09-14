<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The caller must be the project owner or an admin of the target
     * organization; that is enforced by `ProjectPolicy::assignOrganization`
     * in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The target organization must be one the caller owns or administers.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'organization_id' => [
                'nullable',
                'integer',
                Rule::exists('organizations', 'id')->where(function ($query) use ($user) {
                    $query->where('owner_id', $user->id)
                        ->orWhereIn('id', Organization::whereHas('members', function ($members) use ($user) {
                            $members->where('user_id', $user->id)
                                ->where('role', OrganizationRole::Admin);
                        })->pluck('id'));
                }),
            ],
        ];
    }
}
