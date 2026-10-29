@props(['page' => ''])

@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $prefs = \App\Support\Preferences::for(auth()->user());
    $me = auth()->user();
    if ($me) {
        $feedService = app(\App\Services\NotificationFeed::class);
        $feed = $feedService->for($me);
        $unreadMessages = $feedService->unreadMessages($me);
    }
@endphp

<nav class="sticky top-0 z-50 border-b border-white/10 bg-tuwaiq-night/85 backdrop-blur-md" aria-label="{{ __('Main') }}">
    <div class="mx-auto flex max-w-6xl items-center gap-2 px-3 py-2.5 sm:px-4">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="{{ __('QuoteIt home') }}">
            <img src="{{ asset('images/logo.png') }}" class="hidden h-10 w-auto lg:block" alt="Tuwaiq Club">
            <span class="site-name">QuoteIt</span>
        </a>

        <div class="ms-auto flex items-center gap-1 sm:gap-1.5">
            {{-- pages (laptops; phones use the bar at the bottom) --}}
            {{-- laptops: the same icons and words as the phone bar, only at the top --}}
            <a href="{{ route('home') }}" @class(['top-tab', 'is-on' => $page === 'Home']) data-tour="home">
                <x-icon name="home" class="h-6 w-6" /><span>{{ __('Home') }}</span>
            </a>
            <a href="{{ route('gets-me') }}" @class(['top-tab', 'is-on' => $page === 'Gets me']) data-tour="gets-me">
                <x-icon name="sparkles" class="h-6 w-6" /><span>{{ __('Gets me') }}</span>
            </a>
            @auth
                <a href="{{ route('messages.index') }}" @class(['top-tab', 'is-on' => $page === 'Messages']) data-tour="messages">
                    <span class="relative"><x-icon name="chat" class="h-6 w-6" />
                        <span class="count-badge absolute -top-1.5 -end-2.5" data-live="messages" @if (! $unreadMessages) hidden @endif>{{ $unreadMessages }}</span></span>
                    <span>{{ __('Messages') }}</span>
                </a>
            @endauth
            <a href="{{ route('insights') }}" @class(['top-tab', 'is-on' => $page === 'Insights']) data-tour="insights">
                <x-icon name="chart-bar" class="h-6 w-6" /><span>{{ __('Insights') }}</span>
            </a>

            {{-- smart search on its own, so it stands out --}}
            <a href="{{ route('search') }}" class="search-link {{ $page === 'Search' ? 'is-active' : '' }}" title="{{ __('Smart search') }}" aria-label="{{ __('Smart search') }}" data-tour="search">
                <x-icon name="search" />
            </a>

            <span class="mx-1 hidden h-6 w-px bg-white/15 sm:block" aria-hidden="true"></span>

            @auth
                {{-- bell --}}
                <button type="button" popovertarget="bell-menu" class="action relative h-10 w-10" aria-label="{{ __('Notifications') }}" title="{{ __('Notifications') }}" data-bell data-tour="bell">
                    <x-icon name="bell" />
                    <span class="count-badge absolute -top-1 -end-1.5" data-live="notifications" @if (! $feed['count']) hidden @endif>{{ $feed['count'] }}</span>
                </button>
                <div id="bell-menu" popover class="menu fixed top-16 end-3 bottom-auto start-auto w-[min(380px,calc(100vw-24px))]" data-seen-url="{{ route('notifications.seen') }}">
                    <div class="flex items-center justify-between px-3 pt-1 pb-2">
                        <p class="font-bold text-white">{{ __('Notifications') }}</p>
                        <a href="{{ route('profile.edit') }}#notifications" class="text-xs text-tuwaiq-teal hover:underline">{{ __('Settings') }}</a>
                    </div>
                    @forelse ($feed['items'] as $item)
                        <a href="{{ $item['type'] === 'quote' ? route('quotes.show', $item['quote']) : route('messages.show', $item['message']->conversation_id) }}"
                            @class(['flex gap-3 rounded-2xl p-2.5 transition hover:bg-white/5', 'bg-tuwaiq-teal/10' => $item['new']])>
                            <x-avatar :user="$item['user']" size="sm" />
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block"><b class="text-white">{{ $item['user']->name }}</b>
                                    {{ $item['type'] === 'quote' ? __('posted a new quote') : __('sent you a message') }}</span>
                                <span class="block truncate text-tuwaiq-text" dir="auto">{{ $item['type'] === 'quote' ? $item['quote']->content : $item['message']->body }}</span>
                                <span class="block text-xs text-tuwaiq-lilac">{{ $item['at']->diffForHumans() }}</span>
                            </span>
                            @if ($item['new'])<span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-tuwaiq-teal" aria-label="{{ __('New') }}"></span>@endif
                        </a>
                    @empty
                        <p class="px-3 py-6 text-center text-sm text-tuwaiq-lilac">{{ __('Nothing new yet.') }}</p>
                    @endforelse
                    @unless ($me->notify_new_quotes)
                        <p class="mt-1 border-t border-white/10 px-3 pt-2 pb-1 text-xs text-tuwaiq-lilac">
                            {{ __('Want to know when people you follow post?') }}
                            <a href="{{ route('profile.edit') }}#notifications" class="font-semibold text-tuwaiq-teal hover:underline">{{ __('Turn it on') }}</a>
                        </p>
                    @endunless
                </div>
            @endauth


            <a href="{{ route('locale', $otherLocale) }}" class="rounded-full border border-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/10" lang="{{ $otherLocale }}"
                title="{{ $otherLocale === 'ar' ? 'العربية' : 'English' }}" data-tour="language">{{ $otherLocale === 'ar' ? 'ع' : 'EN' }}</a>

            @auth
                <button type="button" popovertarget="user-menu" class="hidden rounded-full p-0.5 hover:bg-white/10 lg:block" aria-label="{{ __('Account menu') }}" title="{{ $me->name }}" data-tour="account">
                    <x-avatar :user="$me" size="sm" />
                </button>
                <div id="user-menu" popover class="menu fixed top-16 end-4 bottom-auto start-auto max-lg:top-auto max-lg:bottom-20">
                    <p class="mb-1 truncate border-b border-white/10 px-3 pt-1 pb-2 text-sm font-semibold text-white">{{ $me->name }}</p>
                    <a href="{{ route('users.show', $me) }}" class="menu-item">{{ __('My profile & stats') }}</a>
                    <a href="{{ route('favorites') }}" class="menu-item">{{ __('My favourites') }}</a>
                    <a href="{{ route('recommendations') }}" class="menu-item">{{ __('Recommended for you') }}</a>
                    <a href="{{ route('achievements') }}" class="menu-item">{{ __('Achievements') }}</a>
                    <a href="{{ route('gets-me.history') }}" class="menu-item">{{ __('My mood history') }}</a>
                    <a href="{{ route('insights') }}" class="menu-item lg:hidden">{{ __('Insights dashboard') }}</a>
                    <a href="{{ route('profile.edit') }}" class="menu-item">{{ __('Edit profile & interests') }}</a>
                    <a href="{{ route('accessibility') }}" class="menu-item">{{ __('Accessibility center') }}</a>
                    <button type="button" class="menu-item" data-tour-start>{{ __('Show the tour again') }}</button>
                    <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-white/10 pt-1">
                        @csrf
                        <button type="submit" class="menu-item text-tuwaiq-orange">{{ __('Log out') }}</button>
                    </form>
                </div>
            @else
                @if ($page !== 'Log In')
                    <a href="{{ route('login') }}" class="btn-ghost hidden px-4 py-2 text-sm whitespace-nowrap lg:inline-flex">{{ __('Log in') }}</a>
                @endif
                @if ($page !== 'Sign Up')
                    <a href="{{ route('signup') }}" class="btn-primary px-2.5 py-2 text-sm whitespace-nowrap sm:px-4" data-tour="signup">{{ __('Sign up') }}</a>
                @endif
            @endauth
        </div>
    </div>
</nav>
