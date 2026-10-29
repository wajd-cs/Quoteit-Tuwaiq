<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\pL\s]+$/u'],
            'bio' => ['nullable', 'string', 'max:160'],
            'contact_link' => [
                'nullable', 'string', 'max:255',
                // link or email
                function (string $attribute, mixed $value, Closure $fail) {
                    $isUrl = filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value);
                    $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL);
                    if (! $isUrl && ! $isEmail) {
                        $fail(__('The contact link must be a web link (https://…) or an email address.'));
                    }
                },
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'notify_new_quotes' => ['nullable', 'boolean'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return ['name.regex' => __('The name may only contain letters and spaces.')];
    }
}
