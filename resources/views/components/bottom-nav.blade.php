@props(['page' => ''])

{{-- phones and tablets: the most used places, always under the thumb --}}
@php $unread = auth()->check() ? app(\App\Services\NotificationFeed::class)->unreadMessages(auth()->user()) : 0; @endphp
<nav class="bottom-nav lg:hidden" aria-label="{{ __('Quick links') }}">
    <a href="{{ route('home') }}" @class(['bottom-tab', 'is-on' => $page === 'Home']) data-tour="home">
        <x-icon name="home" class="h-6 w-6" /><span>{{ __('Home') }}</span>
    </a>
    @auth
        <a href="{{ route('gets-me') }}" @class(['bottom-tab', 'is-on' => $page === 'Gets me']) data-tour="gets-me">
            <x-icon name="sparkles" class="h-6 w-6" /><span>{{ __('Gets me') }}</span>
        </a>
        <button type="button" popovertarget="quote-popup" class="bottom-plus" aria-label="{{ __('Share a quote') }}" data-tour="share">
            <x-icon name="plus" class="h-7 w-7" />
        </button>
        {{-- just the icon here, so the bar stays calm on small screens --}}
        <a href="{{ route('messages.index') }}" @class(['bottom-tab justify-center', 'is-on' => $page === 'Messages']) aria-label="{{ __('Messages') }}" title="{{ __('Messages') }}" data-tour="messages">
            <span class="relative"><x-icon name="chat" class="h-7 w-7" />
                <span class="count-badge absolute -top-1.5 -end-2.5" data-live="messages" @if (! $unread) hidden @endif>{{ $unread }}</span></span>
        </a>
        <button type="button" popovertarget="user-menu" @class(['bottom-tab', 'is-on' => in_array($page, ['Profile', 'Edit profile'], true)]) data-tour="account">
            <x-icon name="user" class="h-6 w-6" /><span>{{ __('Me') }}</span>
        </button>
    @else
        <a href="{{ route('search') }}" @class(['bottom-tab', 'is-on' => $page === 'Search'])>
            <x-icon name="search" class="h-6 w-6" /><span>{{ __('Search') }}</span>
        </a>
        <a href="{{ route('login') }}" class="bottom-plus" aria-label="{{ __('Log in to share a quote') }}">
            <x-icon name="plus" class="h-7 w-7" />
        </a>
        <a href="{{ route('insights') }}" @class(['bottom-tab', 'is-on' => $page === 'Insights'])>
            <x-icon name="chart-bar" class="h-6 w-6" /><span>{{ __('Insights') }}</span>
        </a>
        <a href="{{ route('login') }}" @class(['bottom-tab', 'is-on' => $page === 'Log In'])>
            <x-icon name="user" class="h-6 w-6" /><span>{{ __('Log in') }}</span>
        </a>
    @endauth
</nav>
