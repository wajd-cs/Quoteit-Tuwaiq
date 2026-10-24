@extends('Layout.app')

@section('title', __('Home'))
@section('content')
    @php
        // keep filters when changing tab/category
        $link = fn (array $change) => route('home', array_filter(array_merge(
            ['tab' => $tab === 'all' ? null : $tab, 'category' => $activeCategory?->slug, 'q' => $search ?: null, 'sort' => $sort === 'latest' ? null : $sort],
            $change
        )));
    @endphp

    <div class="mx-auto max-w-6xl px-4 pt-8">

        {{-- Hero + quote of the day --}}
        @if ($showHero)
        <section class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-5">
            <div class="flex flex-col justify-center lg:col-span-2">
                <p class="mb-3 inline-flex w-fit items-center gap-2 rounded-full bg-tuwaiq-teal/15 px-3 py-1 text-xs font-semibold tracking-wide text-tuwaiq-teal uppercase">
                    <x-icon name="sparkles" class="h-4 w-4" /> {{ __('Words worth keeping') }}
                </p>
                <h1 class="text-4xl leading-tight font-bold text-white sm:text-5xl">
                    {{ __('Find quotes that') }} <span class="text-tuwaiq-orange">{{ __('feel like you') }}</span>.
                </h1>
                <p class="mt-4 max-w-md text-tuwaiq-lilac">
                    {{ __('Read what people share, like and save your favourites, follow people with the same taste, and listen to any quote out loud.') }}
                </p>
                @auth
                    <a href="{{ route('gets-me') }}" class="mt-6 flex w-fit items-center gap-3 rounded-2xl border border-tuwaiq-orange/40 bg-tuwaiq-orange/10 px-4 py-3 transition hover:bg-tuwaiq-orange/20">
                        <span><span class="block font-semibold text-white">{{ __('How are you feeling today?') }}</span><span class="block text-sm text-tuwaiq-lilac">Quote That Gets Me · اقتباسي يفهمني</span></span>
                    </a>
                @endauth
                @guest
                    <div class="mt-6 flex gap-3">
                        <a href="{{ route('signup') }}" class="btn-primary">{{ __('Join QuoteIt') }}</a>
                        <a href="{{ route('login') }}" class="btn-ghost">{{ __('Log in') }}</a>
                    </div>
                @endguest
            </div>

            @if ($quoteOfTheDay)
                <div class="lg:col-span-3">
                    <p class="mb-2 text-sm font-semibold text-tuwaiq-orange">{{ __('Quote of the day') }}</p>
                    <x-quote-card :quote="$quoteOfTheDay" large class="quote-of-day" />
                </div>
            @endif
        </section>

        {{-- smart recommendations --}}
        @if ($recommended->isNotEmpty())
            <section class="mt-12" aria-labelledby="rec-title">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 id="rec-title" class="text-xl font-bold text-white">{{ __('Recommended for you') }}</h2>
                        <p class="text-sm text-tuwaiq-lilac">{{ __('Based on the quotes you like and save.') }}</p>
                    </div>
                    <a href="{{ route('recommendations') }}" class="text-sm text-tuwaiq-teal hover:underline">{{ __('See more') }}</a>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-3">
                    @foreach ($recommended as $item)
                        <x-quote-card :quote="$item['quote']">
                            <p class="text-xs text-tuwaiq-teal"><x-icon name="sparkles" class="me-1 inline h-4 w-4" />{{ $item['reason'] }}</p>
                        </x-quote-card>
                    @endforeach
                </div>
            </section>
        @endif
        @endif

        {{-- Feed controls --}}
        <section class="{{ $showHero ? 'mt-12' : 'mt-2' }}" aria-label="{{ __('Quotes') }}">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <nav class="flex w-fit rounded-2xl border border-white/10 bg-tuwaiq-ink/70 p-1" aria-label="{{ __('Feed') }}">
                    <a href="{{ $link(['tab' => null]) }}" class="tab {{ $tab === 'all' ? 'tab-active' : '' }}" @if ($tab === 'all') aria-current="page" @endif>{{ __('All quotes') }}</a>
                    <a href="{{ $link(['tab' => 'for-you']) }}" class="tab {{ $tab === 'for-you' ? 'tab-active' : '' }}" @if ($tab === 'for-you') aria-current="page" @endif>{{ __('For you') }}</a>
                    <a href="{{ $link(['tab' => 'following']) }}" class="tab {{ $tab === 'following' ? 'tab-active' : '' }}" @if ($tab === 'following') aria-current="page" @endif>{{ __('Following') }}</a>
                </nav>

                <form method="GET" action="{{ route('home') }}" class="flex flex-1 gap-2 lg:max-w-md" role="search">
                    @if ($tab !== 'all') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
                    @if ($activeCategory) <input type="hidden" name="category" value="{{ $activeCategory->slug }}"> @endif
                    <label for="q" class="sr-only">{{ __('Search quotes') }}</label>
                    <div class="relative flex-1">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 start-3.5 h-5 w-5 -translate-y-1/2 text-tuwaiq-lilac" />
                        <input id="q" name="q" type="search" value="{{ $search }}" dir="auto" placeholder="{{ __('Search quotes, authors or people…') }}" class="input ps-11" data-search-shortcut>
                    </div>
                    <select name="sort" class="input w-auto" aria-label="{{ __('Sort') }}" onchange="this.form.submit()">
                        @foreach (\App\Models\Quote::sortLabels() as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="no-scrollbar -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="{{ __('Categories') }}">
                <a href="{{ $link(['category' => null]) }}" class="chip {{ $activeCategory ? '' : 'chip-active' }}">{{ __('All') }}</a>
                @foreach ($categories as $category)
                    <a href="{{ $link(['category' => $category->slug]) }}" class="chip {{ $activeCategory?->is($category) ? 'chip-active' : '' }}">{{ $category->label() }}</a>
                @endforeach
            </div>

            @if ($search || $activeCategory)
                <p class="mt-4 text-sm text-tuwaiq-lilac">
                    {{ trans_choice(':count quote|:count quotes', $quotes->total(), ['count' => $quotes->total()]) }}
                    @if ($search) {{ __('for') }} “<span class="text-white">{{ $search }}</span>” @endif
                    @if ($activeCategory) {{ __('in') }} <span class="text-white">{{ $activeCategory->label() }}</span> @endif
                    · <a href="{{ route('home', $tab === 'all' ? [] : ['tab' => $tab]) }}" class="text-tuwaiq-teal hover:underline">{{ __('Clear') }}</a>
                    @if ($search)
                        · <a href="{{ route('search', ['q' => $search]) }}" class="text-tuwaiq-teal hover:underline">{{ __('Try smart search') }}</a>
                    @endif
                </p>
            @endif

            {{-- Quotes grid --}}
            <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($quotes as $quote)
                    <x-quote-card :quote="$quote" />
                @empty
                    <div class="panel p-8 text-center md:col-span-2 lg:col-span-3">
                        @if ($needsSetup)
                            <p class="text-lg font-semibold text-white">{{ __('Tell us what you like') }}</p>
                            <p class="mt-1 text-tuwaiq-lilac">{{ __('Pick a few interests or follow people, and we\'ll fill this page with quotes for you.') }}</p>
                            <a href="{{ route('profile.edit') }}" class="btn-primary mt-4">{{ __('Choose my interests') }}</a>
                        @elseif ($tab === 'following')
                            <p class="text-lg font-semibold text-white">{{ __('You\'re not following anyone yet.') }}</p>
                            <p class="mt-1 text-tuwaiq-lilac">{{ __('Open someone\'s profile from a quote and press Follow.') }}</p>
                        @elseif ($search || $activeCategory)
                            <p class="text-lg font-semibold text-white">{{ __('No quotes match.') }}</p>
                            <p class="mt-1 text-tuwaiq-lilac">{{ __('Try another word or category.') }}</p>
                            @if ($search)
                                <a href="{{ route('search', ['q' => $search]) }}" class="btn-primary mt-4">{{ __('Try smart search') }}</a>
                            @endif
                        @else
                            <p class="text-tuwaiq-lilac">{{ __('No quotes yet. Be the first to share one!') }}</p>
                        @endif
                    </div>
                @endforelse
            </div>

            @include('partials.pagination', ['paginator' => $quotes])
        </section>
    </div>

    @include('partials.share-button')
@endsection
