<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** The chosen publish time, or null to publish now. */
    public function publishAt(): ?Carbon
    {
        return $this->filled('publish_at') ? Carbon::parse($this->input('publish_at'))->seconds(0) : null;
    }

    public function messages(): array
    {
        return [
            'publish_at.after' => __('Choose a time in the future to schedule the quote.'),
            'publish_at.before' => __('You can schedule a quote up to one year ahead.'),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:3', 'max:255'],
            'author' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'moods' => ['nullable', 'array'],
            'moods.*' => ['integer', 'exists:moods,id'],
            'goals' => ['nullable', 'array'],
            'goals.*' => ['integer', 'exists:goals,id'],
            // optional: publish later (Riyadh time), at least a minute from now and within a year
            'publish_at' => ['nullable', 'date', 'after:now', 'before:'.now()->addYear()->toDateTimeString()],
        ];
    }
}
