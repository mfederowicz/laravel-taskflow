<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => [
                'required',
                'integer',
                'not_in:'.$this->route('task')->user_id,
                Rule::exists('users', 'id')
                    ->where('role', UserRole::User->value)
                    ->where('status', UserStatus::Active->value),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
