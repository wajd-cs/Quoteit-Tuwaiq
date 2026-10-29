@extends('Layout.app')

@section('title', Str::limit($quote->content, 40))

@push('meta')
    <meta property="og:title" content="{{ $quote->shareText() }}">
    <meta property="og:description" content="{{ __('Shared on QuoteIt by :name', ['name' => $quote->user->name]) }}">
    <meta property="og:url" content="{{ route('quotes.show', $quote) }}">
@endpush

@section('content')
    <div class="mx-auto max-w-3xl px-4 pt-8">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="mb-4 inline-flex items-center gap-2 text-sm text-tuwaiq-lilac hover:text-white">
            <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" /> {{ __('Back') }}
        </a>

        <x-quote-card :quote="$quote" large />

        {{-- shared by --}}
        <section class="panel mt-6 flex flex-wrap items-center gap-4 p-5" aria-label="{{ __('Shared by') }}">
            <x-avatar :user="$quote->user" size="lg" />
            <div class="min-w-0 flex-1">
                <p class="text-xs tracking-wide text-tuwaiq-lilac uppercase">{{ __('Shared by') }}</p>
                <a href="{{ route('users.show', $quote->user) }}" class="text-lg font-bold text-white hover:underline">{{ $quote->user->name }}</a>
                @if ($quote->user->bio)
                    <p class="text-sm text-tuwaiq-lilac" dir="auto">{{ $quote->user->bio }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('users.show', $quote->user) }}" class="btn-ghost px-4 py-2 text-sm">{{ __('View profile') }}</a>
                @if ($quote->user->contactHref())
                    <a href="{{ $quote->user->contactHref() }}" target="_blank" rel="noopener nofollow" class="btn-ghost px-4 py-2 text-sm">
                        <x-icon name="mail" class="h-4 w-4" /> {{ __('Contact') }}
                    </a>
                @endif
                @auth
                    @unless ($quote->isOwnedBy())
                        <x-follow-button :user="$quote->user" />
                        <x-message-button :user="$quote->user" />
                    @endunless
                @endauth
            </div>
        </section>

        {{-- Comments (opened once a scheduled quote is published) --}}
        @unless ($quote->isHidden())
        <section id="comments" class="mt-10" aria-label="{{ __('Comments') }}">
            <h2 class="text-xl font-bold text-white">{{ __('Comments') }} <span class="text-tuwaiq-lilac">({{ $comments->count() }})</span></h2>

            @auth
                <form method="POST" action="{{ route('comments.store', $quote) }}" class="panel mt-4 p-4">
                    @csrf
                    @include('partials.errors')
                    <label for="comment" class="sr-only">{{ __('Write a comment') }}</label>
                    <textarea id="comment" name="content" rows="2" required minlength="2" maxlength="500" dir="auto" data-counter
                        class="input resize-none" placeholder="{{ __('What does this quote mean to you?') }}">{{ old('content') }}</textarea>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="hint mt-0"><span>{{ __('2–500 characters.') }}</span> <span data-counter-output></span></span>
                        <button type="submit" class="btn-secondary">{{ __('Comment') }}</button>
                    </div>
                </form>
            @else
                <p class="panel mt-4 p-4 text-sm text-tuwaiq-lilac"><a href="{{ route('login') }}" class="font-semibold text-tuwaiq-teal hover:underline">{{ __('Log in') }}</a> {{ __('to join the conversation.') }}</p>
            @endauth

            <ul class="mt-4 space-y-3">
                @forelse ($comments as $comment)
                    <li id="comment-{{ $comment->id }}" class="panel flex gap-3 p-4">
                        <a href="{{ route('users.show', $comment->user) }}"><x-avatar :user="$comment->user" size="sm" /></a>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <a href="{{ route('users.show', $comment->user) }}" class="font-semibold text-white hover:underline">{{ $comment->user->name }}</a>
                                <span class="text-xs text-tuwaiq-lilac">· {{ $comment->created_at->diffForHumans() }}</span>
                            </p>
                            <p class="mt-1 break-words text-tuwaiq-text" dir="auto">{{ $comment->content }}</p>
                        </div>
                        @auth
                            @if ($comment->user_id === auth()->id() || $quote->isOwnedBy())
                                <form method="POST" action="{{ route('comments.destroy', $comment) }}" data-confirm="{{ __('Delete this comment?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action hover:text-red-300" aria-label="{{ __('Delete comment') }}"><x-icon name="trash" class="h-4 w-4" /></button>
                                </form>
                            @endif
                        @endauth
                    </li>
                @empty
                    <li class="text-sm text-tuwaiq-lilac">{{ __('No comments yet. Be the first!') }}</li>
                @endforelse
            </ul>
        </section>
        @endunless

        {{-- Same vibe --}}
        @if ($similar->isNotEmpty())
            <section class="mt-12" aria-label="{{ __('Similar quotes') }}">
                <h2 class="text-xl font-bold text-white">{{ __('Same vibe') }}</h2>
                <p class="text-sm text-tuwaiq-lilac">{{ __('More quotes like this one.') }}</p>
                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-2">
                    @foreach ($similar as $item)
                        <x-quote-card :quote="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
