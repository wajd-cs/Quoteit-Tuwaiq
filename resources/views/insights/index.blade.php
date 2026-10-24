@extends('Layout.app')

@section('title', __('Insights dashboard'))
@section('content')
    @php
        // line chart points
        $w = 640; $h = 220; $pad = 28;
        $max = max(1, $trend->max('likes'), $trend->max('quotes'));
        $step = ($w - 2 * $pad) / max(1, $trend->count() - 1);
        $y = fn ($v) => round($h - $pad - ($v / $max) * ($h - 2 * $pad), 1);
        $line = fn ($key) => $trend->values()->map(fn ($d, $i) => round($pad + $i * $step, 1) . ',' . $y($d[$key]))->implode(' ');
        $labelEvery = max(1, (int) ceil($trend->count() / 7));

        $catMax = max(1, $byCategory->max('quotes'));
        $hourMax = max(1, $hours->max());
        $langTotal = max(1, array_sum($languages));
        $arPct = (int) round($languages['Arabic'] / $langTotal * 100);
        $fmtHour = fn ($hr) => \Illuminate\Support\Carbon::createFromTime($hr)->translatedFormat('g A');

        $ranges = ['7' => __('Last 7 days'), '30' => __('Last 30 days'), '90' => __('Last 90 days'), 'all' => __('All time'), 'custom' => __('Custom dates')];
        $periodText = match ($filters['range']) {
            'all' => __('All time'),
            'custom' => __(':from to :to', ['from' => $filters['from']?->translatedFormat('j M Y') ?? '…', 'to' => $filters['to']->translatedFormat('j M Y')]),
            default => $ranges[$filters['range']],
        };

        $tiles = [
            ['quotes', __('Quotes'), $kpis['quotes'], null, __('Quotes shared in the selected period and topic.')],
            ['users', __('Users'), $kpis['users'], trans_choice(':count active in this period|:count active in this period', $kpis['active_users'], ['count' => $kpis['active_users']]), __('All registered users. “Active” means they shared, liked or commented in the selected period.')],
            ['likes', __('Likes'), $kpis['likes'], __(':value per quote on average', ['value' => $kpis['avg_likes']]), __('Likes given in the period, and the average number of likes each quote received.')],
            ['engagement', __('Engagement rate'), $kpis['engagement'] . '%', __('Based on likes on the shared quotes'), __('It shows how much of what was shared in this period actually reached people: the more quotes that got at least one like, the higher the rate.')],
            ['comments', __('Comments'), $kpis['comments'], null, __('Comments written in the period on these quotes.')],
            ['saves', __('Saved to favourites'), $kpis['saves'], null, __('Times people saved these quotes to their favourites in the period.')],
            ['shares', __('Shares'), $kpis['shares'], null, __('Times these quotes were copied, shared to an app, or saved as an image in the period.')],
        ];
    @endphp

    <div class="mx-auto max-w-6xl px-4 pt-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('QuoteIt in numbers') }}</p>
                <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Insights dashboard') }}</h1>
                <p class="mt-1 text-tuwaiq-lilac">{{ __('What people share, what they like, and when they are active. Updated live from the database.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('insights.report', $filters['query'] + ['lang' => 'ar']) }}" class="btn-primary" target="_blank" rel="noopener" lang="ar"><x-icon name="download" /> {{ __('PDF report') }} · العربية</a>
                <a href="{{ route('insights.report', $filters['query'] + ['lang' => 'en']) }}" class="btn-primary" target="_blank" rel="noopener" lang="en"><x-icon name="download" /> {{ __('PDF report') }} · English</a>
                <a href="{{ route('insights.export', $filters['query']) }}" class="btn-secondary"><x-icon name="download" /> {{ __('Data (CSV)') }}</a>
            </div>
        </div>

        {{-- filters --}}
        <form method="GET" action="{{ route('insights') }}" class="panel mt-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" data-insights-filters>
            <div>
                <label for="f-range" class="label text-sm">{{ __('Period') }}</label>
                <select id="f-range" name="range" class="input" data-range-select>
                    @foreach ($ranges as $value => $label)
                        <option value="{{ $value }}" @selected($filters['range'] === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div data-custom-dates @if ($filters['range'] !== 'custom') hidden @endif>
                <label for="f-from" class="label text-sm">{{ __('From') }}</label>
                <input id="f-from" type="date" name="from" value="{{ $filters['range'] === 'custom' ? $filters['from']?->toDateString() : '' }}" class="input">
            </div>
            <div data-custom-dates @if ($filters['range'] !== 'custom') hidden @endif>
                <label for="f-to" class="label text-sm">{{ __('To') }}</label>
                <input id="f-to" type="date" name="to" value="{{ $filters['range'] === 'custom' ? $filters['to']->toDateString() : '' }}" class="input">
            </div>
            <div>
                <label for="f-category" class="label text-sm">{{ __('Topic') }}</label>
                <select id="f-category" name="category" class="input">
                    <option value="">{{ __('All topics') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($filters['category']?->is($category))>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-secondary flex-1">{{ __('Apply') }}</button>
                @if ($filters['query'])
                    <a href="{{ route('insights') }}" class="btn-ghost">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>
        @include('partials.errors')
        <p class="mt-2 text-sm text-tuwaiq-lilac">
            {{ __('Showing') }}: <span class="text-white">{{ $periodText }}</span> · <span class="text-white">{{ $filters['category']?->label() ?? __('All topics') }}</span>
        </p>

        {{-- KPIs with simple explanations --}}
        {{-- 2 per row on phones, 4 + 3 on tablets and laptops --}}
        <section class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($tiles as [$key, $label, $value, $note, $explain])
                <div @class(['panel relative p-4', 'col-span-2 md:col-span-1' => $loop->last && $loop->count % 2 === 1])>
                    <div class="flex items-start justify-between gap-1">
                        <p class="text-xs text-tuwaiq-lilac">{{ $label }}</p>
                        <button type="button" class="action -m-2 h-8 min-w-8 px-1.5" popovertarget="kpi-{{ $key }}" aria-label="{{ __('What does :name mean?', ['name' => $label]) }}">
                            <x-icon name="info" class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="mt-1 text-3xl font-bold text-white tabular-nums">{{ $value }}</p>
                    @if ($note)<p class="mt-1 text-xs text-tuwaiq-teal">{{ $note }}</p>@endif
                    <div id="kpi-{{ $key }}" popover class="modal max-w-sm">
                        <div class="p-5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-white">{{ $label }}</h3>
                                <button type="button" popovertarget="kpi-{{ $key }}" popovertargetaction="hide" class="action" aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
                            </div>
                            <p class="mt-2 text-sm text-tuwaiq-text">{{ $explain }}</p>
                            @if ($key === 'engagement')
                                <div class="mt-3 rounded-xl bg-white/5 p-3 text-sm">
                                    <p class="font-semibold text-white">{{ __('What it is based on') }}</p>
                                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-tuwaiq-text">
                                        <li>{{ __('Only the quotes shared in the selected period and topic.') }}</li>
                                        <li>{{ __('A quote counts as “liked” if it got at least one like, no matter how many.') }}</li>
                                        <li>{{ __('Comments, saves and shares are shown separately and are not part of this rate.') }}</li>
                                    </ul>
                                    <p class="mt-2 text-xs text-tuwaiq-lilac">{{ __('A higher rate means more of the shared quotes reach and touch people.') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- engagement explained in plain words --}}
        <section class="panel mt-6 grid grid-cols-1 gap-4 p-5 md:grid-cols-3">
            <div class="md:col-span-2">
                <h2 class="font-bold text-white">{{ __('How is the engagement rate calculated?') }}</h2>
                <p class="mt-1 text-sm text-tuwaiq-text">{{ __('It shows how much of what was shared in this period actually reached people: the more quotes that got at least one like, the higher the rate.') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-tuwaiq-text">
                    <li>{{ __('Only the quotes shared in the selected period and topic.') }}</li>
                    <li>{{ __('A quote counts as “liked” if it got at least one like, no matter how many.') }}</li>
                    <li>{{ __('Comments, saves and shares are shown separately and are not part of this rate.') }}</li>
                </ul>
            </div>
            <div class="flex flex-col items-center justify-center">
                <div class="relative h-28 w-28 rounded-full" style="background: conic-gradient(var(--color-tuwaiq-teal) 0 {{ $kpis['engagement'] }}%, rgba(255,255,255,.1) {{ $kpis['engagement'] }}% 100%)" role="img" aria-label="{{ __('Engagement rate') }} {{ $kpis['engagement'] }}%">
                    <div class="absolute inset-3 flex items-center justify-center rounded-full bg-tuwaiq-ink text-2xl font-bold text-white">{{ $kpis['engagement'] }}%</div>
                </div>
            </div>
        </section>

        @if ($findings)
            <section class="panel mt-6 p-5">
                <h2 class="font-bold text-white">{{ __('Key findings') }}</h2>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-tuwaiq-text">
                    @foreach ($findings as $finding)
                        <li>{{ $finding }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <section class="panel p-5 lg:col-span-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-white">{{ trans_choice('Activity, last :count day|Activity, last :count days', $trend->count(), ['count' => $trend->count()]) }}</h2>
                    <div class="flex gap-4 text-xs">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-tuwaiq-orange"></span> {{ __('Likes') }}</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-tuwaiq-teal"></span> {{ __('New quotes') }}</span>
                    </div>
                </div>
                <svg viewBox="0 0 {{ $w }} {{ $h + 20 }}" class="mt-3 w-full" dir="ltr" role="img" aria-label="{{ __('Line chart of new quotes and likes per day') }}">
                    @foreach ([0, 0.5, 1] as $g)
                        <line x1="{{ $pad }}" x2="{{ $w - $pad }}" y1="{{ $y($max * $g) }}" y2="{{ $y($max * $g) }}" stroke="#ffffff" stroke-opacity=".08" />
                        <text x="{{ $pad - 6 }}" y="{{ $y($max * $g) + 4 }}" text-anchor="end" font-size="10" fill="#938bb8">{{ round($max * $g) }}</text>
                    @endforeach
                    <polyline points="{{ $line('likes') }}" fill="none" stroke="var(--color-tuwaiq-orange)" stroke-width="2.5" stroke-linejoin="round" />
                    <polyline points="{{ $line('quotes') }}" fill="none" stroke="var(--color-tuwaiq-teal)" stroke-width="2.5" stroke-linejoin="round" stroke-dasharray="6 3" />
                    @foreach ($trend->values() as $i => $d)
                        <circle cx="{{ round($pad + $i * $step, 1) }}" cy="{{ $y($d['likes']) }}" r="3" fill="var(--color-tuwaiq-orange)"><title>{{ $d['date'] }}: {{ $d['likes'] }} {{ __('likes') }}</title></circle>
                        <rect x="{{ round($pad + $i * $step, 1) - 3 }}" y="{{ $y($d['quotes']) - 3 }}" width="6" height="6" fill="var(--color-tuwaiq-teal)"><title>{{ $d['date'] }}: {{ $d['quotes'] }} {{ __('quotes') }}</title></rect>
                        @if ($i % $labelEvery === 0)
                            <text x="{{ round($pad + $i * $step, 1) }}" y="{{ $h + 10 }}" text-anchor="middle" font-size="10" fill="#938bb8">{{ \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('j M') }}</text>
                        @endif
                    @endforeach
                </svg>
            </section>

            <section class="panel p-5 lg:col-span-2">
                <h2 class="font-bold text-white">{{ __('Quote language') }}</h2>
                <div class="mt-4 flex items-center gap-6">
                    <div class="relative h-36 w-36 shrink-0 rounded-full" style="background: conic-gradient(var(--color-tuwaiq-teal) 0 {{ $arPct }}%, var(--color-tuwaiq-purple-light) {{ $arPct }}% 100%)" role="img" aria-label="{{ $arPct }}% {{ __('Arabic') }}">
                        <div class="absolute inset-5 flex items-center justify-center rounded-full bg-tuwaiq-ink text-2xl font-bold text-white">{{ $arPct }}%</div>
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-tuwaiq-teal"></span> {{ __('Arabic') }} · {{ $languages['Arabic'] }}</li>
                        <li class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-tuwaiq-purple-light"></span> {{ __('English') }} · {{ $languages['English'] }}</li>
                        <li class="pt-2 text-xs text-tuwaiq-lilac">{{ __('Average length: :count characters', ['count' => $avgLength]) }}</li>
                    </ul>
                </div>
            </section>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="panel p-5">
                <h2 class="font-bold text-white">{{ __('Quotes and likes by topic') }}</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($byCategory as $c)
                        <a href="{{ route('home', ['category' => $c['slug']]) }}" class="block hover:opacity-90">
                            <div class="flex justify-between gap-2 text-sm"><span>{{ $c['name'] }}</span><span class="text-tuwaiq-lilac tabular-nums">{{ trans_choice(':count quote|:count quotes', $c['quotes'], ['count' => $c['quotes']]) }} · {{ trans_choice(':count like|:count likes', $c['likes'], ['count' => $c['likes']]) }}</span></div>
                            <div class="mt-1 h-2.5 rounded-full bg-white/5">
                                <div class="h-full rounded-full bg-gradient-to-r from-tuwaiq-purple-light to-tuwaiq-teal rtl:bg-gradient-to-l" style="width: {{ $c['quotes'] / $catMax * 100 }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="panel p-5">
                <h2 class="font-bold text-white">{{ __('When people are active') }}</h2>
                <p class="text-xs text-tuwaiq-lilac">{{ __('Quotes, likes and comments by hour (Riyadh time)') }}</p>
                <div class="mt-4 flex h-40 items-end gap-1" dir="ltr">
                    @foreach ($hours as $hour => $count)
                        <div class="flex h-full flex-1 flex-col justify-end" title="{{ $fmtHour($hour) }}: {{ $count }}">
                            <div class="rounded-t {{ $hour === $peakHour && $count ? 'bg-tuwaiq-orange' : 'bg-tuwaiq-purple-light' }}" style="height: {{ max(2, $count / $hourMax * 100) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-[10px] text-tuwaiq-lilac" dir="ltr"><span>{{ $fmtHour(0) }}</span><span>{{ $fmtHour(6) }}</span><span>{{ $fmtHour(12) }}</span><span>{{ $fmtHour(18) }}</span><span>{{ $fmtHour(23) }}</span></div>
            </section>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <section class="panel p-5 lg:col-span-3">
                <h2 class="font-bold text-white">{{ __('Most liked quotes') }}</h2>
                <ol class="mt-3 divide-y divide-white/10">
                    @forelse ($topQuotes as $i => $q)
                        <li class="flex items-start gap-3 py-3">
                            <span class="mt-0.5 w-5 text-sm font-bold text-tuwaiq-orange">{{ $i + 1 }}</span>
                            <a href="{{ route('quotes.show', $q) }}" class="min-w-0 flex-1 hover:underline">
                                <p class="font-quote truncate text-white" dir="auto">{{ $q->content }}</p>
                                <p class="text-xs text-tuwaiq-lilac">{{ __('by :name', ['name' => $q->user->name]) }} @if ($q->category) · {{ $q->category->label() }} @endif</p>
                            </a>
                            <span class="text-sm whitespace-nowrap text-tuwaiq-orange tabular-nums">{{ trans_choice(':count like|:count likes', $q->likes_count, ['count' => $q->likes_count]) }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-tuwaiq-lilac">{{ __('No quotes in this period.') }}</li>
                    @endforelse
                </ol>
            </section>

            <section class="panel p-5 lg:col-span-2">
                <h2 class="font-bold text-white">{{ __('Top contributors') }}</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="text-start text-xs text-tuwaiq-lilac">
                        <tr><th class="pb-2 text-start font-medium">{{ __('User') }}</th><th class="pb-2 text-end font-medium">{{ __('Quotes') }}</th><th class="pb-2 text-end font-medium">{{ __('Likes') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($topPeople as $person)
                            <tr>
                                <td class="py-2"><a href="{{ route('users.show', $person) }}" class="flex items-center gap-2 hover:underline"><x-avatar :user="$person" size="sm" /> {{ $person->name }}</a></td>
                                <td class="py-2 text-end tabular-nums">{{ $person->quotes_count }}</td>
                                <td class="py-2 text-end tabular-nums">{{ $person->likes_received }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-tuwaiq-lilac">{{ __('No quotes in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
@endsection
