<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class QuotePickRequest extends FormRequest
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
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'mood' => ['required', 'string', 'exists:moods,slug'],
            'goal' => ['required', 'string', 'exists:goals,slug'],
        ];
    }
}
