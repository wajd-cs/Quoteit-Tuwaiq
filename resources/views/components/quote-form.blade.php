@props(['quote' => null, 'id' => 'quote-popup'])

{{-- same form for new quote and edit --}}
@php
    $editing = $quote !== null;
    $action = $editing ? route('edit_quote', $quote) : route('quotes.store');
    $field = fn ($name) => $editing ? $id . '-' . $name : $name;
    $useOld = $editing ? (int) old('editing_id') === $quote->id : ! old('editing_id');
@endphp

<div id="{{ $id }}" popover class="modal">
    <form method="POST" action="{{ $action }}" class="space-y-4 p-6">
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="editing_id" value="{{ $quote->id }}">
        @endif

        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-white">{{ $editing ? __('Edit your quote') : __('Share a quote') }}</h2>
            <button type="button" popovertarget="{{ $id }}" popovertargetaction="hide" class="action" aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
        </div>

        @if ($useOld)
            @include('partials.errors')
        @endif

        <div>
            <label for="{{ $field('content') }}" class="label">{{ __('Quote') }}</label>
            <textarea id="{{ $field('content') }}" name="content" rows="4" required minlength="3" maxlength="155" dir="auto"
                data-counter placeholder="{{ __('Write your quote…') }}"
                class="input peer resize-none user-invalid:border-red-500">{{ $useOld ? old('content', $quote?->content) : $quote?->content }}</textarea>
            <p class="hint flex justify-between peer-user-invalid:text-red-300"><span>{{ __('3–155 characters.') }}</span><span data-counter-output></span></p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $field('author') }}" class="label">{{ __('Said by') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></label>
                <input id="{{ $field('author') }}" name="author" type="text" maxlength="80" dir="auto" class="input"
                    placeholder="{{ __('e.g. Ibn Khaldun') }}" value="{{ $useOld ? old('author', $quote?->author) : $quote?->author }}">
                <p class="hint">{{ __('Leave empty if it\'s your own words.') }}</p>
            </div>
            <div>
                <label for="{{ $field('category') }}" class="label">{{ __('Category') }}</label>
                @php $selected = $useOld ? old('category_id', $quote?->category_id) : $quote?->category_id; @endphp
                <select id="{{ $field('category') }}" name="category_id" class="input">
                    <option value="">{{ __('No category') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $selected === (string) $category->id)>{{ $category->label() }}</option>
                    @endforeach
                </select>
                <p class="hint">{{ __('Helps people with the same taste find it.') }}</p>
            </div>
        </div>

        {{-- look of the quote: fixed presets that always stay readable --}}
        @php
            $style = array_merge(\App\Support\QuoteStyle::DEFAULT, ($useOld ? old('style', $quote?->style) : $quote?->style) ?? []);
            $styled = $style !== \App\Support\QuoteStyle::DEFAULT;
        @endphp
        <details class="rounded-2xl border border-white/10 p-3" data-style-picker @if ($styled) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-white">{{ __('Font, size and colour') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></summary>

            <p class="quote-text mt-3 rounded-2xl bg-white/5 p-3 {{ \App\Support\QuoteStyle::classes($style) }}" dir="auto" data-style-preview>{{ __('Your quote will look like this') }}</p>

            <p class="mt-3 text-xs font-semibold text-tuwaiq-lilac">{{ __('Font') }}</p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                @foreach (\App\Support\QuoteStyle::FONTS as $key => $label)
                    <label class="style-chip qs-font-{{ $key }}">
                        <input type="radio" name="style[font]" value="{{ $key }}" class="sr-only" @checked($style['font'] === $key)> {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold text-tuwaiq-lilac">{{ __('Size') }}</p>
                    <div class="mt-1 flex gap-1.5">
                        @foreach (\App\Support\QuoteStyle::SIZES as $key => $label)
                            <label class="style-chip"><input type="radio" name="style[size]" value="{{ $key }}" class="sr-only" @checked($style['size'] === $key)> {{ __($label) }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-tuwaiq-lilac">{{ __('Alignment') }}</p>
                    <div class="mt-1 flex gap-1.5">
                        @foreach (\App\Support\QuoteStyle::ALIGNS as $key => $label)
                            <label class="style-chip"><input type="radio" name="style[align]" value="{{ $key }}" class="sr-only" @checked($style['align'] === $key)> {{ __($label) }}</label>
                        @endforeach
                    </div>
                </div>
            </div>

            <p class="mt-3 text-xs font-semibold text-tuwaiq-lilac">{{ __('Colour') }}</p>
            <div class="mt-1 flex flex-wrap gap-2">
                @foreach (\App\Support\QuoteStyle::COLORS as $color)
                    <label class="color-dot qs-dot-{{ $color }}" title="{{ __('Colour: :name', ['name' => __(ucfirst($color))]) }}">
                        <input type="radio" name="style[color]" value="{{ $color }}" class="sr-only" @checked($style['color'] === $color)>
                        <span class="sr-only">{{ __(ucfirst($color)) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="hint">{{ __('Every colour is picked to stay easy to read.') }}</p>
        </details>

        {{-- publish later: new quotes, or my quote that is still waiting --}}
        @if (! $editing || $quote->isScheduled() || $quote->is_draft)
            @php
                $publishValue = $useOld ? old('publish_at', $quote?->published_at?->format('Y-m-d\TH:i')) : $quote?->published_at?->format('Y-m-d\TH:i');
                $minValue = now()->addMinutes(2)->format('Y-m-d\TH:i');
            @endphp
            @if ($editing)
                <input type="hidden" name="schedule_section" value="1">
            @endif
            <details class="rounded-2xl border border-white/10 p-3" @if ($publishValue) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-white">{{ __('Publish later') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></summary>
                <label for="{{ $field('publish_at') }}" class="label mt-3 text-sm">{{ __('Day and time (Riyadh time)') }}</label>
                <input id="{{ $field('publish_at') }}" type="datetime-local" name="publish_at" value="{{ $publishValue }}" min="{{ $minValue }}" class="input" data-schedule-input>
                <p class="hint">{{ __('The quote stays hidden and is published automatically at this time. Leave empty to publish now.') }}</p>
            </details>
        @endif

        {{-- used by Quote That Gets Me --}}
        @php
            $chosenGoals = collect($useOld ? old('goals', $quote?->goals->pluck('id')->all() ?? []) : ($quote?->goals->pluck('id')->all() ?? []))->map(fn ($v) => (int) $v);
            $chosenMoods = collect($useOld ? old('moods', $quote?->moods->pluck('id')->all() ?? []) : ($quote?->moods->pluck('id')->all() ?? []))->map(fn ($v) => (int) $v);
        @endphp
        <input type="hidden" name="feelings_section" value="1">
        <details class="rounded-2xl border border-white/10 p-3" @if ($chosenGoals->isNotEmpty() || $chosenMoods->isNotEmpty()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-white">{{ __('How can this quote help?') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></summary>
            <p class="hint mt-2">{{ __('Helps “Quote That Gets Me” suggest it to the right people.') }}</p>
            <p class="mt-3 text-xs font-semibold text-tuwaiq-lilac">{{ __('Good for') }}</p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                @foreach ($formGoals as $goal)
                    <label class="chip cursor-pointer px-2.5 py-1 text-xs has-checked:border-tuwaiq-teal has-checked:bg-tuwaiq-teal/15 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-tuwaiq-teal">
                        <input type="checkbox" name="goals[]" value="{{ $goal->id }}" class="sr-only" @checked($chosenGoals->contains($goal->id))>
                        {{ $goal->label() }}
                    </label>
                @endforeach
            </div>
            <p class="mt-3 text-xs font-semibold text-tuwaiq-lilac">{{ __('When someone feels') }}</p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                @foreach ($formMoods as $mood)
                    <label class="chip cursor-pointer px-2.5 py-1 text-xs has-checked:border-tuwaiq-orange has-checked:bg-tuwaiq-orange/15 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-tuwaiq-teal">
                        <input type="checkbox" name="moods[]" value="{{ $mood->id }}" class="sr-only" @checked($chosenMoods->contains($mood->id))>
                        {{ $mood->label() }}
                    </label>
                @endforeach
            </div>
        </details>

        @php
            $isDraft = $editing && $quote->is_draft;
            $mainLabel = $isDraft ? __('Publish') : ($editing ? __('Save') : __('Send'));
        @endphp
        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            <button type="submit" class="btn-primary w-full sm:flex-1" data-submit-label="{{ $mainLabel }}" data-schedule-label="{{ __('Schedule') }}">{{ $mainLabel }}</button>
            @if (! $editing || $isDraft)
                <button type="submit" name="draft" value="1" class="btn-ghost w-full sm:w-auto">
                    <x-icon name="draft" class="h-4 w-4" /> {{ $isDraft ? __('Keep as draft') : __('Save as draft') }}
                </button>
            @endif
        </div>
        @if (! $editing)
            <p class="hint mt-0 text-center">{{ __('A draft is saved in your profile and only you can see it.') }}</p>
        @endif
    </form>
</div>
