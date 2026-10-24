<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SuggestQuoteRequest extends FormRequest
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
            'mood' => ['required', 'string', 'exists:moods,slug'],
            'goal' => ['required', 'string', 'exists:goals,slug'],
            'except' => ['nullable', 'array', 'max:50'],
            'except.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'mood.required' => __('Choose how you feel first.'),
            'goal.required' => __('Choose what you need right now.'),
            'mood.exists' => __('Please choose one of the feelings in the list.'),
            'goal.exists' => __('Please choose one of the goals in the list.'),
        ];
    }
}
