<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'security_question' => ['nullable', 'string', 'max:255'],
            'security_answer' => [
                'nullable',
                'string',
                'required_with:security_question',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (filled($value) && ! filled($this->input('security_question'))) {
                        $fail('The :attribute can only be provided together with a security question.');
                    }
                },
            ],
        ];
    }
}
