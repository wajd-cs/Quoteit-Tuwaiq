@auth
    {{-- + button --}}
    <button type="button" popovertarget="quote-popup" aria-label="{{ __('Share a quote') }}" title="{{ __('Share a quote') }}"
        class="fab fixed end-6 bottom-6 z-40 flex h-16 w-16 items-center justify-center rounded-full bg-tuwaiq-orange text-tuwaiq-night shadow-xl shadow-black/40 transition hover:scale-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-tuwaiq-orange/40">
        <x-icon name="plus" class="h-8 w-8" />
    </button>

    <x-quote-form />

    {{-- reopen the form if there were errors --}}
    @if ($errors->hasAny(['content', 'author', 'category_id', 'moods', 'goals', 'publish_at']))
        <span hidden data-open-popover="{{ old('editing_id') ? 'edit-' . old('editing_id') : 'quote-popup' }}"></span>
    @endif
@else
    <a href="{{ route('login') }}"
        class="fab fixed end-6 bottom-6 z-40 rounded-full bg-tuwaiq-orange px-5 py-3 text-sm font-semibold sm:px-6 sm:py-4 sm:text-base text-tuwaiq-night shadow-xl shadow-black/40 transition hover:scale-105">
        {{ __('Log in to share a quote') }}
    </a>
@endauth
