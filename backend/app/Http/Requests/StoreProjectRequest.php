<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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
