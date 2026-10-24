@props(['quote', 'large' => false])

@php
    $isMine = $quote->isOwnedBy();
    $quoteUrl = route('quotes.show', $quote);
    $shareText = $quote->shareText();
    $isArabic = (bool) preg_match('/\p{Arabic}/u', $quote->content);
    $postedAt = $quote->published_at ?? $quote->created_at;
    $scheduled = $quote->isScheduled();
    $sharedUrl = route('quotes.shared', $quote);
@endphp

<article id="quote-{{ $quote->id }}" {{ $attributes->merge(['class' => 'panel group relative flex flex-col p-5 sm:p-6 ' . ($isMine ? 'ring-1 ring-tuwaiq-purple-light/70 bg-gradient-to-br from-tuwaiq-purple/50 to-tuwaiq-ink/80' : '')]) }}
    aria-label="{{ __('Quote by :name', ['name' => $quote->author ?: $quote->user->name]) }}">

    <header class="mb-3 flex items-center gap-3">
        <a href="{{ route('users.show', $quote->user) }}" class="flex min-w-0 items-center gap-2.5 hover:opacity-90" title="{{ __('View :name\'s profile', ['name' => $quote->user->name]) }}">
            <x-avatar :user="$quote->user" size="sm" />
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-white">{{ $quote->user->name }} @if ($isMine)<span class="font-normal text-tuwaiq-lilac">({{ __('you') }})</span>@endif</span>
                <span class="block text-xs text-tuwaiq-lilac">{{ $postedAt->format('Y-m-d') }} · {{ $postedAt->diffForHumans() }}</span>
            </span>
        </a>

        @if ($quote->category)
            <a href="{{ route('home', ['category' => $quote->category->slug]) }}" class="chip ms-auto px-2.5 py-1 text-xs">
                {{ $quote->category->label() }}
            </a>
        @endif
    </header>

    <a href="{{ $quoteUrl }}" class="block flex-1">
        <blockquote>
            <p class="quote-text {{ $large ? 'quote-text-lg' : '' }} text-white" dir="{{ $isArabic ? 'rtl' : 'ltr' }}" lang="{{ $isArabic ? 'ar' : 'en' }}">{{ $isArabic ? '«' : '“' }}{{ $quote->content }}{{ $isArabic ? '»' : '”' }}</p>
            @if ($quote->author)
                <footer class="mt-3 text-sm text-tuwaiq-orange" dir="auto">— {{ $quote->author }}</footer>
            @endif
        </blockquote>
    </a>

    @if ($scheduled)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-tuwaiq-orange/40 bg-tuwaiq-orange/10 p-3 text-sm">
            <span class="flex items-center gap-2">
                <x-icon name="clock" class="h-5 w-5 text-tuwaiq-orange" />
                <span>{{ __('Scheduled for :date', ['date' => $quote->published_at->translatedFormat('j M Y · g:i A')]) }}
                    <span class="block text-xs text-tuwaiq-lilac">{{ __('Only you can see it until then.') }}</span></span>
            </span>
            <form method="POST" action="{{ route('quotes.publish-now', $quote) }}">
                @csrf
                <button type="submit" class="btn-secondary px-3 py-1.5 text-sm">{{ __('Publish now') }}</button>
            </form>
        </div>
    @endif

    {{-- Actions --}}
    <div class="mt-4 flex flex-wrap items-center gap-0.5 border-t border-white/10 pt-3">
        {{-- no likes, saves or comments until a scheduled quote goes live --}}
        @if ($scheduled)
        @elseauth
            <form method="POST" action="{{ route('quotes.like', $quote) }}" data-toggle="like">
                @csrf
                <button type="submit" class="action action-like" aria-pressed="{{ $quote->liked_by_me ? 'true' : 'false' }}" title="{{ __('Like') }}">
                    <x-icon name="heart" />
                    <span data-count>{{ $quote->likes_count }}</span>
                    <span class="sr-only">{{ __('likes') }}</span>
                </button>
            </form>
            <form method="POST" action="{{ route('quotes.bookmark', $quote) }}" data-toggle="bookmark">
                @csrf
                <button type="submit" class="action action-save" aria-pressed="{{ $quote->bookmarked_by_me ? 'true' : 'false' }}" title="{{ __('Save to favourites') }}" aria-label="{{ __('Save to favourites') }}">
                    <x-icon name="bookmark" />
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="action" title="{{ __('Log in to like') }}"><x-icon name="heart" /> <span>{{ $quote->likes_count }}</span><span class="sr-only">{{ __('likes') }}</span></a>
            <a href="{{ route('login') }}" class="action" title="{{ __('Log in to save') }}" aria-label="{{ __('Log in to save') }}"><x-icon name="bookmark" /></a>
        @endif

        @unless ($scheduled)
            <a href="{{ $quoteUrl }}#comments" class="action" title="{{ __('Comments') }}">
                <x-icon name="chat" /> <span>{{ $quote->comments_count }}</span><span class="sr-only">{{ __('comments') }}</span>
            </a>

            <button type="button" class="action" data-copy="{{ $shareText }}&#10;— via QuoteIt {{ $quoteUrl }}" data-share-channel="copy" data-share-url="{{ $sharedUrl }}" title="{{ __('Copy quote') }}" aria-label="{{ __('Copy quote') }}">
                <x-icon name="copy" />
            </button>
        @endunless

        <button type="button" class="action" data-speak="{{ $quote->content }}{{ $quote->author ? '. ' . $quote->author : '' }}" data-lang="{{ $isArabic ? 'ar-SA' : 'en-US' }}" title="{{ __('Listen') }}" aria-label="{{ __('Read the quote aloud') }}">
            <x-icon name="speaker" />
        </button>

        @unless ($scheduled)
            <a href="{{ route('studio', $quote) }}" class="action" title="{{ __('Design as an image') }}" aria-label="{{ __('Design as an image') }}">
                <x-icon name="palette" />
            </a>

            <button type="button" class="action" popovertarget="share-{{ $quote->id }}" title="{{ __('Share') }}" aria-label="{{ __('Share') }}">
                <x-icon name="share" />
            </button>
        @endunless

        @if ($isMine)
            <span class="ms-auto flex items-center gap-1">
                <button type="button" class="action" popovertarget="edit-{{ $quote->id }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit quote') }}">
                    <x-icon name="pencil" />
                </button>
                <form method="POST" action="{{ route('delete_quote', $quote) }}" data-confirm="{{ __('Delete this quote? This cannot be undone.') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="action hover:text-red-300" title="{{ __('Delete') }}" aria-label="{{ __('Delete quote') }}">
                        <x-icon name="trash" />
                    </button>
                </form>
            </span>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="mt-3 border-t border-white/10 pt-3">{{ $slot }}</div>
    @endif

    @unless ($scheduled)
        <x-share-dialog :quote="$quote" :url="$quoteUrl" :text="$shareText" />
    @endunless

    @if ($isMine)
        <x-quote-form :quote="$quote" id="edit-{{ $quote->id }}" />
    @endif
</article>
