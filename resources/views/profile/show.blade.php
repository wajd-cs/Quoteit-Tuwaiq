@extends('Layout.app')

@section('title', $user->name)
@section('content')
    <div class="mx-auto max-w-6xl px-4 pt-8">

        {{-- Header --}}
        <section class="panel overflow-hidden">
            <div class="h-24 bg-gradient-to-r from-tuwaiq-purple via-tuwaiq-purple-light to-tuwaiq-teal/70"></div>
            <div class="flex flex-col gap-5 px-6 pb-6 sm:flex-row sm:items-end">
                <x-avatar :user="$user" size="xl" class="-mt-12 ring-4 ring-tuwaiq-ink" />
                <div class="min-w-0 flex-1">
                    <h1 class="text-3xl font-bold text-white">{{ $user->name }}</h1>
                    @if ($user->bio)
                        <p class="mt-1 text-tuwaiq-lilac" dir="auto">{{ $user->bio }}</p>
                    @endif
                    <p class="mt-1 text-xs text-tuwaiq-lilac/80">{{ __('Joined :date', ['date' => $user->created_at->translatedFormat('F Y')]) }}</p>
                    @if ($user->interests->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($user->interests as $interest)
                                <a href="{{ route('home', ['category' => $interest->slug]) }}" class="chip px-2.5 py-1 text-xs">{{ $interest->label() }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($isMe)
                        <a href="{{ route('profile.edit') }}" class="btn-ghost px-4 py-2 text-sm"><x-icon name="pencil" class="h-4 w-4" /> {{ __('Edit profile') }}</a>
                    @else
                        @if ($user->contactHref())
                            <a href="{{ $user->contactHref() }}" target="_blank" rel="noopener nofollow" class="btn-ghost px-4 py-2 text-sm"><x-icon name="mail" class="h-4 w-4" /> {{ __('Contact') }}</a>
                        @endif
                        @auth
                            <x-follow-button :user="$user" />
                        @else
                            <a href="{{ route('login') }}" class="btn-secondary px-4 py-2 text-sm"><x-icon name="user-plus" class="h-4 w-4" /> {{ __('Follow') }}</a>
                        @endauth
                    @endif
                </div>
            </div>
        </section>

        {{-- Stats --}}
        @php
            $tiles = [
                ['Quotes shared', $stats['quotes']],
                ['Likes received', $stats['likes_received']],
                ['Quotes liked', $stats['likes_given']],
                ['Saved', $stats['saved']],
                ['Comments', $stats['comments']],
                ['Followers', $stats['followers']],
                ['Following', $stats['following']],
                ['Points', $points],
            ];
        @endphp
        <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" aria-label="{{ __('Stats') }}">
            @foreach ($tiles as [$label, $value])
                <div class="panel p-4">
                    <p class="text-xs text-tuwaiq-lilac">{{ __($label) }}</p>
                    <p class="mt-1 text-3xl font-bold text-white tabular-nums" @if ($label === 'Followers') data-followers-count @endif>{{ $value }}</p>
                </div>
            @endforeach
        </section>

        {{-- weekly summary (only the owner sees it) --}}
        @if ($weekly)
            @php
                $diff = $weekly['likes'] - $weekly['likes_before'];
                $topText = $weekly['top_quote'] ? \Illuminate\Support\Str::limit($weekly['top_quote']->content, 90) : null;
            @endphp
            <section class="panel mt-6 p-5" aria-labelledby="weekly-title">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="weekly-title" class="font-bold text-white">{{ __('Your week') }}</h2>
                    <p class="text-xs text-tuwaiq-lilac">{{ __('The last 7 days, only you can see this.') }}</p>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-2xl bg-white/5 p-3">
                        <dt class="text-xs text-tuwaiq-lilac">{{ __('Likes on your quotes') }}</dt>
                        <dd class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $weekly['likes'] }}</dd>
                        <dd class="text-xs {{ $diff > 0 ? 'text-tuwaiq-teal' : 'text-tuwaiq-lilac' }}">
                            @if ($diff > 0)
                                {{ __(':count more than the week before', ['count' => $diff]) }}
                            @elseif ($diff < 0)
                                {{ __(':count fewer than the week before', ['count' => abs($diff)]) }}
                            @else
                                {{ __('Same as the week before') }}
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-3">
                        <dt class="text-xs text-tuwaiq-lilac">{{ __('Comments on your quotes') }}</dt>
                        <dd class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $weekly['comments'] }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-3">
                        <dt class="text-xs text-tuwaiq-lilac">{{ __('Times your quotes were shared') }}</dt>
                        <dd class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $weekly['shares'] }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-3">
                        <dt class="text-xs text-tuwaiq-lilac">{{ __('New quotes you shared') }}</dt>
                        <dd class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $weekly['new_quotes'] }}</dd>
                    </div>
                </dl>

                <ul class="mt-4 space-y-2 text-sm">
                    <li class="flex gap-2">
                        <x-icon name="sparkles" class="mt-0.5 h-4 w-4 shrink-0 text-tuwaiq-orange" />
                        @if ($weekly['top_quote'])
                            <span class="min-w-0">{{ __('Your quote people interacted with most:') }}
                                <span class="text-tuwaiq-lilac">({{ trans_choice(':count interaction|:count interactions', $weekly['top_score'], ['count' => $weekly['top_score']]) }})</span>
                                <a href="{{ route('quotes.show', $weekly['top_quote']) }}" class="mt-1 block font-semibold text-white hover:underline" dir="auto">{{ $topText }}</a></span>
                        @else
                            <span class="text-tuwaiq-lilac">{{ __('No likes, comments or shares on your quotes this week yet.') }}</span>
                        @endif
                    </li>
                    @if ($weekly['top_topic'])
                        <li class="flex gap-2">
                            <x-icon name="chart" class="mt-0.5 h-4 w-4 shrink-0 text-tuwaiq-teal" />
                            <span>{{ __('You write most about :topic', ['topic' => $weekly['top_topic']]) }}
                                <span class="text-tuwaiq-lilac">({{ trans_choice(':count quote|:count quotes', $weekly['top_topic_count'], ['count' => $weekly['top_topic_count']]) }})</span></span>
                        </li>
                    @endif
                </ul>
            </section>
        @endif

        {{-- badges --}}
        <section class="panel mt-6 p-5" aria-labelledby="badges-title">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="badges-title" class="font-bold text-white">{{ __('Badges') }} <span class="text-tuwaiq-lilac">({{ $badges->count() }})</span></h2>
                @if ($isMe)
                    <a href="{{ route('achievements') }}" class="text-sm text-tuwaiq-teal hover:underline">{{ __('All achievements') }}</a>
                @endif
            </div>
            @if ($badges->isEmpty())
                <p class="mt-2 text-sm text-tuwaiq-lilac">{{ __('No badges yet.') }}</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($badges as $badge)
                        <li class="flex items-center gap-2 rounded-full border border-tuwaiq-orange/40 bg-tuwaiq-orange/10 px-3 py-1.5 text-sm text-white" title="{{ $badge['description'] }}">
                            <x-icon name="trophy" class="h-4 w-4 text-tuwaiq-orange" /> {{ $badge['name'] }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($taste->isNotEmpty())
            <section class="panel mt-6 p-5" aria-label="{{ __('Taste') }}">
                <h2 class="font-bold text-white">{{ $isMe ? __('Your taste') : __('Taste') }}</h2>
                <p class="text-xs text-tuwaiq-lilac">{{ $isMe ? __('Categories of the quotes you liked') : __('Categories of the quotes :name liked', ['name' => $user->name]) }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($taste as $name => $count)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-32 shrink-0 truncate">{{ $name }}</span>
                            <div class="h-2.5 flex-1 rounded-full bg-white/5">
                                <div class="h-full rounded-full bg-gradient-to-r from-tuwaiq-purple-light to-tuwaiq-orange" style="width: {{ $count / $taste->max() * 100 }}%"></div>
                            </div>
                            <span class="w-14 text-end text-tuwaiq-lilac tabular-nums">{{ round($count / $taste->sum() * 100) }}%</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Tabs --}}
        <nav class="mt-8 flex w-fit rounded-2xl border border-white/10 bg-tuwaiq-ink/70 p-1" aria-label="{{ __('Profile sections') }}">
            <a href="{{ route('users.show', $user) }}" class="tab {{ $tab === 'quotes' ? 'tab-active' : '' }}">{{ $isMe ? __('My quotes') : __('Quotes') }}</a>
            <a href="{{ route('users.show', [$user, 'tab' => 'liked']) }}" class="tab {{ $tab === 'liked' ? 'tab-active' : '' }}">{{ __('Liked') }}</a>
            @if ($isMe)
                <a href="{{ route('users.show', [$user, 'tab' => 'saved']) }}" class="tab {{ $tab === 'saved' ? 'tab-active' : '' }}">{{ __('Favourites') }}</a>
                <a href="{{ route('users.show', [$user, 'tab' => 'scheduled']) }}" class="tab {{ $tab === 'scheduled' ? 'tab-active' : '' }}">{{ __('Scheduled') }} @if ($scheduledCount)<span class="ms-1 rounded-full bg-tuwaiq-orange px-1.5 text-xs text-tuwaiq-night tabular-nums">{{ $scheduledCount }}</span>@endif</a>
            @endif
        </nav>

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($quotes as $quote)
                <x-quote-card :quote="$quote" />
            @empty
                <p class="panel p-8 text-center text-tuwaiq-lilac md:col-span-2 lg:col-span-3">
                    @switch($tab)
                        @case('saved') {{ __('Nothing saved yet. Press the bookmark on any quote to keep it here.') }} @break
                        @case('liked') {{ __('No liked quotes yet.') }} @break
                        @case('scheduled') {{ __('No scheduled quotes. When you share a quote, open “Publish later” to pick a day and time.') }} @break
                        @default {{ $isMe ? __('You haven\'t shared a quote yet. Press + to share your first one!') : __('No quotes shared yet.') }}
                    @endswitch
                </p>
            @endforelse
        </div>

        @include('partials.pagination', ['paginator' => $quotes])
    </div>

    @if ($isMe)
        @include('partials.share-button')
    @endif
@endsection
