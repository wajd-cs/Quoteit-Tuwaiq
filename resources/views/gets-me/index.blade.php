@extends('Layout.app')

@section('title', __('Quote That Gets Me'))
@section('content')
    @php $isAr = app()->getLocale() === 'ar'; @endphp
    <div class="mx-auto max-w-5xl px-4 pt-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-tuwaiq-teal">{{ $isAr ? 'Quote That Gets Me' : 'اقتباسي يفهمني' }}</p>
                <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Quote That Gets Me') }}</h1>
                <p class="mt-1 max-w-xl text-tuwaiq-lilac">{{ __('Tell us how you feel and what you need right now, and we\'ll find quotes that fit.') }}</p>
            </div>
            <a href="{{ route('gets-me.history') }}" class="btn-ghost px-4 py-2 text-sm">{{ __('My history') }}</a>
        </div>

        <form method="GET" action="{{ route('gets-me.suggest') }}" class="panel mt-6 space-y-6 p-5 sm:p-7" data-gets-me novalidate>
            <div data-gets-me-error hidden class="rounded-2xl border border-red-400/40 bg-red-500/15 p-3 text-sm text-red-100" role="alert"></div>
            @include('partials.errors')

            <fieldset>
                <legend class="label text-lg">{{ __('1. How do you feel?') }}</legend>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($moods as $mood)
                        <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white/15 p-3 transition hover:border-white/35 has-checked:border-tuwaiq-orange has-checked:bg-tuwaiq-orange/15 has-focus-visible:ring-2 has-focus-visible:ring-tuwaiq-teal">
                            <input type="radio" name="mood" value="{{ $mood->slug }}" class="sr-only" required
                                @checked(old('mood', $selectedMood?->slug) === $mood->slug)>
                            <span class="leading-tight"><span class="block font-semibold text-white">{{ $mood->label() }}</span><span class="block text-sm text-tuwaiq-lilac" lang="{{ $isAr ? 'en' : 'ar' }}">{{ $isAr ? $mood->name : $mood->name_ar }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="label text-lg">{{ __('2. What do you need?') }}</legend>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-5">
                    @foreach ($goals as $goal)
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border border-white/15 p-3 text-center transition hover:border-white/35 has-checked:border-tuwaiq-teal has-checked:bg-tuwaiq-teal/15 has-focus-visible:ring-2 has-focus-visible:ring-tuwaiq-teal">
                            <input type="radio" name="goal" value="{{ $goal->slug }}" class="sr-only" required
                                @checked(old('goal', $selectedGoal?->slug) === $goal->slug)>
                            <span class="font-semibold text-white">{{ $goal->label() }}</span>
                            <span class="text-sm text-tuwaiq-lilac" lang="{{ $isAr ? 'en' : 'ar' }}">{{ $isAr ? $goal->name : $goal->name_ar }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="btn-secondary w-full py-3 text-base sm:w-auto" data-gets-me-submit>
                <span data-idle>{{ __('Suggest a quote') }}</span>
                <span data-busy hidden class="items-center gap-2">
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4"/></svg>
                    {{ __('Finding quotes…') }}
                </span>
            </button>
        </form>

        {{-- results --}}
        <section class="mt-8" aria-live="polite" aria-busy="false" data-gets-me-results>
            @if ($quotes !== null)
                @include('gets-me.partials.results', ['quotes' => $quotes, 'mood' => $selectedMood, 'goal' => $selectedGoal, 'goalCategoryIds' => $goalCategoryIds])
            @endif
        </section>

        {{-- loading placeholders (shown by app.js) --}}
        <template data-gets-me-skeleton>
            <p class="sr-only">{{ __('Finding quotes…') }}</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                @for ($i = 0; $i < 3; $i++)
                    <div class="panel animate-pulse p-6">
                        <div class="flex items-center gap-3"><div class="h-8 w-8 rounded-full bg-white/10"></div><div class="h-3 w-24 rounded bg-white/10"></div></div>
                        <div class="mt-5 h-4 rounded bg-white/10"></div>
                        <div class="mt-2 h-4 w-4/5 rounded bg-white/10"></div>
                        <div class="mt-2 h-4 w-3/5 rounded bg-white/10"></div>
                    </div>
                @endfor
            </div>
        </template>

        @if ($recentPicks->isNotEmpty())
            <section class="mt-12">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white">{{ __('Recently picked') }}</h2>
                    <a href="{{ route('gets-me.history') }}" class="text-sm text-tuwaiq-teal hover:underline">{{ __('See all') }}</a>
                </div>
                <ul class="mt-3 space-y-2">
                    @foreach ($recentPicks as $pick)
                        <li class="panel flex flex-wrap items-center gap-x-4 gap-y-1 p-4 text-sm">
                            <span class="text-tuwaiq-lilac">{{ $pick->created_at->translatedFormat('j M') }}</span>
                            <span>{{ $pick->mood->label() }} · {{ $pick->goal->label() }}</span>
                            <a href="{{ route('quotes.show', $pick->quote) }}" class="min-w-0 flex-1 truncate font-quote text-white hover:underline" dir="auto">{{ $pick->quote->content }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    @include('partials.share-button')
@endsection
