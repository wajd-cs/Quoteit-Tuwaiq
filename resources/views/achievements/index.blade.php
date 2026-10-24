@extends('Layout.app')

@section('title', __('Achievements'))
@section('content')
    @php $levelProgress = $points % 100; @endphp
    <div class="mx-auto max-w-6xl px-4 pt-8">
        <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('Your journey on QuoteIt') }}</p>
        <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Achievements') }}</h1>

        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
            {{-- level --}}
            <section class="panel p-5" aria-label="{{ __('Level') }}">
                <p class="text-sm text-tuwaiq-lilac">{{ __('Level') }}</p>
                <p class="text-5xl font-bold text-white tabular-nums">{{ $level }}</p>
                <p class="mt-1 text-sm"><span class="font-semibold text-tuwaiq-orange tabular-nums">{{ $points }}</span> {{ __('points') }}</p>
                <div class="mt-3 h-2.5 rounded-full bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $levelProgress }}" aria-label="{{ __('Progress to the next level') }}">
                    <div class="h-full rounded-full bg-gradient-to-r from-tuwaiq-purple-light to-tuwaiq-orange" style="width: {{ $levelProgress }}%"></div>
                </div>
                <p class="mt-1 text-xs text-tuwaiq-lilac">{{ __(':points points to level :level', ['points' => $nextLevelAt - $points, 'level' => $level + 1]) }}</p>
            </section>

            {{-- daily challenge --}}
            <section class="panel p-5 lg:col-span-2 {{ $challenge['done'] ? 'ring-1 ring-tuwaiq-teal' : '' }}" aria-label="{{ __('Daily quote challenge') }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-tuwaiq-orange">{{ __('Daily quote challenge') }}</p>
                        <p class="mt-1 text-xl font-bold text-white">{{ $challenge['text'] }}</p>
                        <p class="text-sm text-tuwaiq-lilac">{{ __('+:points points · a new challenge every day', ['points' => $challenge['points']]) }}</p>
                    </div>
                    @if ($challenge['done'])
                        <span class="rounded-full bg-tuwaiq-teal/20 px-3 py-1 text-sm font-semibold text-tuwaiq-teal">{{ __('Done today') }}</span>
                    @endif
                </div>
                <div class="mt-4 h-2.5 rounded-full bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $challenge['target'] }}" aria-valuenow="{{ $challenge['progress'] }}" aria-label="{{ __('Challenge progress') }}">
                    <div class="h-full rounded-full bg-tuwaiq-teal" style="width: {{ $challenge['progress'] / $challenge['target'] * 100 }}%"></div>
                </div>
                <p class="mt-1 text-xs text-tuwaiq-lilac tabular-nums">{{ $challenge['progress'] }} / {{ $challenge['target'] }} · {{ trans_choice(':count challenge completed so far|:count challenges completed so far', $challengeDays, ['count' => $challengeDays]) }}</p>
            </section>
        </div>

        {{-- badges --}}
        <h2 class="mt-10 text-xl font-bold text-white">{{ __('Badges') }}</h2>
        <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($badges as $badge)
                <li class="panel flex gap-3 p-4 {{ $badge['earned_at'] ? 'ring-1 ring-tuwaiq-orange/60' : 'opacity-80' }}">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $badge['earned_at'] ? 'bg-tuwaiq-orange text-tuwaiq-night' : 'bg-white/10 text-tuwaiq-lilac' }}" aria-hidden="true">
                        <x-icon name="trophy" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-white">{{ $badge['name'] }}</p>
                        <p class="text-xs text-tuwaiq-lilac">{{ $badge['description'] }} · +{{ $badge['points'] }}</p>
                        @if ($badge['earned_at'])
                            <p class="mt-1 text-xs text-tuwaiq-orange">{{ __('Earned :date', ['date' => \Illuminate\Support\Carbon::parse($badge['earned_at'])->translatedFormat('j M Y')]) }}</p>
                        @else
                            <div class="mt-2 h-1.5 rounded-full bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $badge['target'] }}" aria-valuenow="{{ $badge['progress'] }}" aria-label="{{ $badge['name'] }}">
                                <div class="h-full rounded-full bg-tuwaiq-lilac" style="width: {{ $badge['progress'] / $badge['target'] * 100 }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-tuwaiq-lilac tabular-nums">{{ $badge['progress'] }} / {{ $badge['target'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="panel p-5">
                <h2 class="font-bold text-white">{{ __('Leaderboard') }}</h2>
                <p class="text-xs text-tuwaiq-lilac">{{ __('Points from badges and daily challenges') }}</p>
                <ol class="mt-3 divide-y divide-white/10">
                    @forelse ($leaderboard as $i => $row)
                        <li class="flex items-center gap-3 py-2.5 {{ $row['user']->is(auth()->user()) ? 'text-tuwaiq-orange' : '' }}">
                            <span class="w-6 text-center font-bold tabular-nums">{{ $i + 1 }}</span>
                            <a href="{{ route('users.show', $row['user']) }}" class="flex min-w-0 flex-1 items-center gap-2 hover:underline">
                                <x-avatar :user="$row['user']" size="sm" /> <span class="truncate">{{ $row['user']->name }}</span>
                            </a>
                            <span class="font-semibold tabular-nums">{{ $row['points'] }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-tuwaiq-lilac">{{ __('No points yet. Be the first!') }}</li>
                    @endforelse
                </ol>
            </section>

            <section class="panel p-5">
                <h2 class="font-bold text-white">{{ __('Most active this week') }}</h2>
                <p class="text-xs text-tuwaiq-lilac">{{ __('Quotes ×3, comments ×2, likes ×1 in the last 7 days') }}</p>
                <ol class="mt-3 space-y-2">
                    @forelse ($mostActive as $i => $row)
                        <li class="flex items-center gap-3 rounded-xl bg-white/5 p-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full font-bold {{ ['bg-tuwaiq-orange text-tuwaiq-night', 'bg-tuwaiq-lilac text-tuwaiq-night', 'bg-tuwaiq-purple-light text-white'][$i] ?? 'bg-white/10' }}">{{ $i + 1 }}</span>
                            <a href="{{ route('users.show', $row['user']) }}" class="flex-1 truncate hover:underline">{{ $row['user']->name }}</a>
                            <span class="text-sm text-tuwaiq-lilac tabular-nums">{{ $row['score'] }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-tuwaiq-lilac">{{ __('No activity this week yet.') }}</li>
                    @endforelse
                </ol>
            </section>
        </div>
    </div>
@endsection
