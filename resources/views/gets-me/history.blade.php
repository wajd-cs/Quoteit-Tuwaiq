@extends('Layout.app')

@section('title', __('My history'))
@section('content')
    <div class="mx-auto max-w-5xl px-4 pt-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('Quote That Gets Me') }}</p>
                <h1 class="text-3xl font-bold text-white">{{ __('My history') }}</h1>
                <p class="mt-1 text-tuwaiq-lilac">{{ __('Quotes you picked, with how you felt and what you needed. Only you can see this page.') }}</p>
            </div>
            <a href="{{ route('gets-me') }}" class="btn-secondary">{{ __('Find a new quote') }}</a>
        </div>

        @if ($moodCounts->isNotEmpty())
            <nav class="no-scrollbar -mx-4 mt-6 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="{{ __('Filter by feeling') }}">
                <a href="{{ route('gets-me.history') }}" class="chip {{ $activeMood ? '' : 'chip-active' }}">{{ __('All') }} · {{ $moodCounts->sum() }}</a>
                @foreach ($moods as $mood)
                    @if ($moodCounts->has($mood->id))
                        <a href="{{ route('gets-me.history', ['mood' => $mood->slug]) }}" class="chip {{ $activeMood === $mood->slug ? 'chip-active' : '' }}">{{ $mood->label() }} · {{ $moodCounts[$mood->id] }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">
            @forelse ($picks as $pick)
                <x-quote-card :quote="$pick->quote">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div class="flex flex-wrap gap-x-3 gap-y-1">
                            <span><span class="text-tuwaiq-lilac">{{ __('Felt') }}</span> {{ $pick->mood->label() }}</span>
                            <span><span class="text-tuwaiq-lilac">{{ __('Needed') }}</span> {{ $pick->goal->label() }}</span>
                            <time class="text-tuwaiq-lilac" datetime="{{ $pick->created_at->toIso8601String() }}">{{ $pick->created_at->translatedFormat('j M Y · g:i A') }}</time>
                        </div>
                        <form method="POST" action="{{ route('gets-me.destroy', $pick) }}" data-confirm="{{ __('Remove this from your history?') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-tuwaiq-lilac hover:text-red-300">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </x-quote-card>
            @empty
                <div class="panel p-8 text-center md:col-span-2">
                    <p class="text-lg font-semibold text-white">{{ __('Nothing here yet.') }}</p>
                    <p class="mt-1 text-tuwaiq-lilac">{{ __('Pick a feeling and a goal, then press “This gets me” on a quote you like.') }}</p>
                    <a href="{{ route('gets-me') }}" class="btn-primary mt-4">{{ __('Start') }}</a>
                </div>
            @endforelse
        </div>

        @include('partials.pagination', ['paginator' => $picks])
    </div>

    @include('partials.share-button')
@endsection
