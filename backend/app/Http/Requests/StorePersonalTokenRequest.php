<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
