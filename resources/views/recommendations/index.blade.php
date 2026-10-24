@extends('Layout.app')

@section('title', __('Recommended for you'))
@section('content')
    <div class="mx-auto max-w-6xl px-4 pt-8">
        <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('Smart recommendations') }}</p>
        <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Recommended for you') }}</h1>
        <p class="mt-1 max-w-2xl text-tuwaiq-lilac">{{ __('We learn your taste from the quotes you like, save and pick, and from the people you follow. Each card tells you why it was suggested.') }}</p>

        <section class="panel mt-6 p-5" aria-label="{{ __('Your taste') }}">
            <h2 class="font-bold text-white">{{ __('What we learned about your taste') }}</h2>
            @if ($taste['signals'] === 0 && $taste['interests']->isEmpty() && $taste['following']->isEmpty())
                <p class="mt-1 text-sm text-tuwaiq-lilac">{{ __('Nothing yet. Like or save a few quotes and your recommendations will get better.') }}</p>
            @else
                <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                    @foreach ($topCategories as $category)
                        <li class="chip chip-active">{{ __('You like :category', ['category' => $category->label()]) }}</li>
                    @endforeach
                    @foreach ($taste['authors']->keys()->take(2) as $authorName)
                        <li class="chip" dir="auto">{{ __('You like quotes by :author', ['author' => $authorName]) }}</li>
                    @endforeach
                    @if ($taste['following']->isNotEmpty())
                        <li class="chip">{{ trans_choice('You follow :count person|You follow :count people', $taste['following']->count(), ['count' => $taste['following']->count()]) }}</li>
                    @endif
                </ul>
                <p class="mt-2 text-xs text-tuwaiq-lilac">{{ trans_choice('Based on :count quote you liked, saved or picked.|Based on :count quotes you liked, saved or picked.', $taste['signals'], ['count' => $taste['signals']]) }}</p>
            @endif
        </section>

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($items as $item)
                <x-quote-card :quote="$item['quote']">
                    <p class="flex items-start gap-2 text-xs {{ $item['personal'] ? 'text-tuwaiq-teal' : 'text-tuwaiq-lilac' }}">
                        <x-icon name="sparkles" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ $item['reason'] }}</span>
                    </p>
                    @if ($item['extra'])
                        <ul class="mt-1 space-y-0.5 ps-6 text-[11px] text-tuwaiq-lilac">
                            @foreach ($item['extra'] as $extra)
                                <li>{{ __('Also:') }} {{ $extra }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-quote-card>
            @empty
                <div class="panel p-8 text-center md:col-span-2 lg:col-span-3">
                    <p class="text-lg font-semibold text-white">{{ __('You have seen everything for now.') }}</p>
                    <p class="mt-1 text-tuwaiq-lilac">{{ __('Come back when people share new quotes.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    @include('partials.share-button')
@endsection
