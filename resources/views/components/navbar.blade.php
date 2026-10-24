@props(['page' => ''])

@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $prefs = \App\Support\Preferences::for(auth()->user());
    $themeLabel = $prefs['theme'] === 'light' ? __('Switch to dark mode') : __('Switch to light mode');
@endphp

<nav class="sticky top-0 z-50 border-b border-white/10 bg-tuwaiq-night/85 backdrop-blur-md" aria-label="{{ __('Main') }}">
    <div class="mx-auto flex max-w-6xl items-center gap-2 px-3 py-2.5 sm:px-4">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="{{ __('QuoteIt home') }}">
            <span class="hidden lg:block">
                <img src="{{ asset('images/logo.png') }}" class="logo-dark h-10 w-auto" alt="Tuwaiq Club">
                <img src="{{ asset('images/logo-light.png') }}" class="logo-light h-10 w-auto" alt="Tuwaiq Club">
            </span>
            <span class="site-name">QuoteIt</span>
        </a>

        <div class="ms-auto flex items-center gap-1 sm:gap-1.5">
            <a href="{{ route('home') }}" class="tab hidden lg:inline-flex {{ $page === 'Home' ? 'tab-active' : '' }}">{{ __('Home') }}</a>
            <a href="{{ route('gets-me') }}" class="tab hidden lg:inline-flex {{ $page === 'Gets me' ? 'tab-active' : '' }}">{{ __('Gets me') }}</a>
            <a href="{{ route('insights') }}" class="tab hidden md:inline-flex {{ $page === 'Insights' ? 'tab-active' : '' }}">{{ __('Insights') }}</a>

            {{-- smart search on its own, so it stands out --}}
            <a href="{{ route('search') }}" class="search-link {{ $page === 'Search' ? 'is-active' : '' }}" title="{{ __('Smart search') }}" aria-label="{{ __('Smart search') }}">
                <x-icon name="search" />
            </a>

            <span class="mx-1 hidden h-6 w-px bg-white/15 sm:block" aria-hidden="true"></span>

            {{-- on phones the accessibility center is in the menu --}}
            <a href="{{ route('accessibility') }}" class="action hidden sm:inline-flex {{ $page === 'Accessibility' ? 'bg-white/10 text-white' : '' }}" title="{{ __('Accessibility center') }}" aria-label="{{ __('Accessibility center') }}">
                <x-icon name="accessibility" />
            </a>

            {{-- light / dark --}}
            <button type="button" class="action" data-theme-toggle aria-label="{{ $themeLabel }}" title="{{ $themeLabel }}">
                <x-icon name="sun" class="theme-icon-sun" />
                <x-icon name="moon" class="theme-icon-moon" />
            </button>

            <a href="{{ route('locale', $otherLocale) }}" class="rounded-full border border-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/10" lang="{{ $otherLocale }}"
                title="{{ $otherLocale === 'ar' ? 'العربية' : 'English' }}">{{ $otherLocale === 'ar' ? 'ع' : 'EN' }}</a>

            @auth
                <button type="button" popovertarget="user-menu" class="rounded-full p-0.5 hover:bg-white/10" aria-label="{{ __('Account menu') }}" title="{{ auth()->user()->name }}">
                    <x-avatar :user="auth()->user()" size="sm" />
                </button>
                <div id="user-menu" popover class="menu fixed top-16 end-4 bottom-auto start-auto">
                    <p class="mb-1 truncate border-b border-white/10 px-3 pt-1 pb-2 text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                    <a href="{{ route('home') }}" class="menu-item lg:hidden">{{ __('Home') }}</a>
                    <a href="{{ route('users.show', auth()->user()) }}" class="menu-item">{{ __('My profile & stats') }}</a>
                    <a href="{{ route('favorites') }}" class="menu-item">{{ __('My favourites') }}</a>
                    <a href="{{ route('recommendations') }}" class="menu-item">{{ __('Recommended for you') }}</a>
                    <a href="{{ route('achievements') }}" class="menu-item">{{ __('Achievements') }}</a>
                    <a href="{{ route('gets-me') }}" class="menu-item">{{ __('Quote That Gets Me') }}</a>
                    <a href="{{ route('gets-me.history') }}" class="menu-item">{{ __('My mood history') }}</a>
                    <a href="{{ route('search') }}" class="menu-item">{{ __('Smart search') }}</a>
                    <a href="{{ route('home', ['tab' => 'for-you']) }}" class="menu-item">{{ __('For you') }}</a>
                    <a href="{{ route('profile.edit') }}" class="menu-item">{{ __('Edit profile & interests') }}</a>
                    <a href="{{ route('accessibility') }}" class="menu-item">{{ __('Accessibility center') }}</a>
                    <a href="{{ route('insights') }}" class="menu-item">{{ __('Insights dashboard') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-white/10 pt-1">
                        @csrf
                        <button type="submit" class="menu-item text-tuwaiq-orange">{{ __('Log out') }}</button>
                    </form>
                </div>
            @else
                {{-- phones and tablets: the page links live in a small menu --}}
                <button type="button" popovertarget="guest-menu" class="action lg:hidden" aria-label="{{ __('Menu') }}" title="{{ __('Menu') }}">
                    <x-icon name="menu" />
                </button>
                <div id="guest-menu" popover class="menu fixed top-16 end-4 bottom-auto start-auto">
                    <a href="{{ route('home') }}" class="menu-item">{{ __('Home') }}</a>
                    <a href="{{ route('search') }}" class="menu-item">{{ __('Smart search') }}</a>
                    <a href="{{ route('insights') }}" class="menu-item">{{ __('Insights dashboard') }}</a>
                    <a href="{{ route('gets-me') }}" class="menu-item">{{ __('Quote That Gets Me') }}</a>
                    <a href="{{ route('accessibility') }}" class="menu-item">{{ __('Accessibility center') }}</a>
                    @if ($page !== 'Log In')
                        <a href="{{ route('login') }}" class="menu-item border-t border-white/10 text-tuwaiq-orange">{{ __('Log in') }}</a>
                    @endif
                </div>
                @if ($page !== 'Log In')
                    <a href="{{ route('login') }}" class="btn-ghost hidden px-4 py-2 text-sm whitespace-nowrap sm:inline-flex">{{ __('Log in') }}</a>
                @endif
                @if ($page !== 'Sign Up')
                    <a href="{{ route('signup') }}" class="btn-primary px-2.5 py-2 text-sm whitespace-nowrap sm:px-4">{{ __('Sign up') }}</a>
                @endif
            @endauth
        </div>
    </div>
</nav>
