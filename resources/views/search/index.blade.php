@extends('Layout.app')

@section('title', __('Smart search'))
@section('content')
    <div class="mx-auto max-w-5xl px-4 pt-8">
        <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('Search the way you talk') }}</p>
        <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Smart search') }}</h1>
        <p class="mt-1 max-w-2xl text-tuwaiq-lilac">{{ __('Write what you are looking for in your own words, in Arabic or English. You can also search by topic or by who said it.') }}</p>

        <form method="GET" action="{{ route('search') }}" class="panel mt-6 space-y-4 p-5 sm:p-6" role="search">
            <div>
                <label for="smart-q" class="label">{{ __('What are you looking for?') }}</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 start-3.5 h-5 w-5 -translate-y-1/2 text-tuwaiq-lilac" />
                    <input id="smart-q" name="q" type="search" value="{{ $query }}" dir="auto" maxlength="200" autofocus data-search-shortcut
                        placeholder="{{ __('e.g. I want a quote about success after failure') }}" class="input py-3 ps-11 text-base">
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-tuwaiq-lilac">{{ __('Try:') }}</span>
                    @foreach ($examples as $example)
                        <a href="{{ route('search', ['q' => $example]) }}" class="chip px-2.5 py-1 text-xs" dir="auto">{{ $example }}</a>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="smart-category" class="label text-sm">{{ __('Topic') }}</label>
                    <select id="smart-category" name="category" class="input">
                        <option value="">{{ __('Any topic') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected($activeCategory?->is($category))>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="smart-author" class="label text-sm">{{ __('Said by') }}</label>
                    <select id="smart-author" name="author" class="input" dir="auto">
                        <option value="">{{ __('Anyone') }}</option>
                        @foreach ($authors as $name)
                            <option value="{{ $name }}" @selected($author === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="smart-sort" class="label text-sm">{{ __('Sort') }}</label>
                    <select id="smart-sort" name="sort" class="input">
                        @foreach (\App\Models\Quote::sortLabels(true) as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="btn-secondary w-full sm:w-auto sm:px-10">{{ __('Search') }}</button>
                </div>
            </div>
        </form>

        @if ($results !== null)
            <section class="mt-8" aria-live="polite">
                @if ($understood && ($understood['topics'] || $understood['keywords']))
                    <div class="panel p-4 text-sm">
                        <p class="font-semibold text-white">{{ __('Here is what we understood:') }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($understood['categories'] as $c)
                                <span class="chip chip-active px-2.5 py-1 text-xs">{{ __('Topic') }}: {{ $c->label() }}</span>
                            @endforeach
                            @foreach ($understood['goals'] as $g)
                                <span class="chip px-2.5 py-1 text-xs">{{ __('Helps with') }}: {{ $g->label() }}</span>
                            @endforeach
                            @foreach ($understood['moods'] as $m)
                                <span class="chip px-2.5 py-1 text-xs">{{ __('Feeling') }}: {{ $m->label() }}</span>
                            @endforeach
                            @foreach ($understood['keywords'] as $k)
                                <span class="chip px-2.5 py-1 text-xs" dir="auto">“{{ $k }}”</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <h2 class="mt-6 text-xl font-bold text-white">
                    {{ trans_choice(':count quote found|:count quotes found', $results->total(), ['count' => $results->total()]) }}
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @forelse ($results as $row)
                        <x-quote-card :quote="$row['quote']">
                            <p class="text-xs text-tuwaiq-lilac">
                                @if ($row['matched'])
                                    {{ __('Contains') }}: <span class="text-white" dir="auto">{{ implode('، ', $row['matched']) }}</span>
                                @else
                                    {{ __('Matches the topic of your search') }}
                                @endif
                            </p>
                        </x-quote-card>
                    @empty
                        <div class="panel p-8 text-center md:col-span-2 lg:col-span-3">
                            <p class="text-lg font-semibold text-white">{{ __('No quotes match yet.') }}</p>
                            <p class="mt-1 text-tuwaiq-lilac">{{ __('Try fewer or different words, or pick a topic.') }}</p>
                        </div>
                    @endforelse
                </div>

                @include('partials.pagination', ['paginator' => $results])
            </section>
        @endif
    </div>
@endsection
