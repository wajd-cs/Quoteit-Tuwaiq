@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $periodText = match ($filters['range']) {
        'all' => __('All time'),
        'custom' => __(':from to :to', ['from' => $filters['from']?->translatedFormat('j M Y') ?? '…', 'to' => $filters['to']->translatedFormat('j M Y')]),
        '7' => __('Last 7 days'),
        '30' => __('Last 30 days'),
        '90' => __('Last 90 days'),
    };
    $catMax = max(1, $byCategory->max('quotes'));
    $interestMax = max(1, $interests->max(fn ($r) => max($r['chosen'], $r['liked'])));
    $moodMax = max(1, $feelings['moods']->max('total'));
    $goalMax = max(1, $feelings['goals']->max('total'));
    $langTotal = max(1, array_sum($languages));
    $arPct = (int) round($languages['Arabic'] / $langTotal * 100);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('QuoteIt insights report') }} · {{ $generatedAt->format('Y-m-d') }}</title>
    <style>
        @font-face { font-family: 'Almarai'; src: url('{{ asset('fonts/Almarai-Regular.ttf') }}'); font-weight: 400; }
        @font-face { font-family: 'Almarai'; src: url('{{ asset('fonts/Almarai-Bold.ttf') }}'); font-weight: 700; }
        @font-face { font-family: 'Readex Pro'; src: url('{{ asset('fonts/ReadexPro.ttf') }}'); font-weight: 160 700; }
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Almarai', 'Readex Pro', Arial, sans-serif; color: #1d1530; background: #ece8f6; font-size: 12.5px; line-height: 1.55; }
        .sheet { max-width: 210mm; margin: 0 auto; background: #fff; padding: 14mm; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; padding: 12px; background: #10081f; }
        .toolbar button, .toolbar a { font: inherit; font-weight: 700; border: 0; border-radius: 12px; padding: 10px 18px; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #f2a54a; color: #10081f; }
        .toolbar .ghost { background: transparent; color: #fff; border: 1px solid #ffffff44; }
        .toolbar button:active, .toolbar a:active { transform: scale(.96); }
        .toolbar p { color: #c9c3e0; margin: 0 8px; align-self: center; font-size: 12px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #432a80; padding-bottom: 10px; }
        h1 { font-size: 24px; margin: 0; color: #432a80; }
        h2 { font-size: 15px; color: #432a80; margin: 18px 0 6px; border-bottom: 1px solid #ddd6f3; padding-bottom: 4px; break-after: avoid; }
        .muted { color: #6b6385; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 12px; }
        .kpi { border: 1px solid #ddd6f3; border-radius: 10px; padding: 8px 10px; break-inside: avoid; }
        .kpi .v { font-size: 22px; font-weight: 700; color: #1d1530; }
        .kpi .l { font-size: 11px; color: #6b6385; }
        .kpi .e { font-size: 10.5px; color: #4b4366; margin-top: 2px; }
        .formula { background: #f5f2fd; border-radius: 8px; padding: 8px 10px; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: 5px 6px; border-bottom: 1px solid #eee9fa; vertical-align: top; }
        th { font-size: 11px; color: #6b6385; font-weight: 700; }
        .num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .bar { height: 8px; border-radius: 4px; background: #eee9fa; overflow: hidden; min-width: 80px; }
        .bar > span { display: block; height: 100%; background: #432a80; }
        .bar.alt > span { background: #f2a54a; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        section { break-inside: avoid; }
        ul { margin: 4px 0; padding-inline-start: 18px; }
        footer { margin-top: 18px; font-size: 10px; color: #6b6385; border-top: 1px solid #ddd6f3; padding-top: 6px; }
        /* phones: the report still reads well before saving */
        @media screen and (max-width: 640px) {
            .toolbar { flex-wrap: wrap; padding: 8px; }
            .toolbar p { flex-basis: 100%; text-align: center; }
            .sheet { padding: 16px; }
            header { flex-direction: column; gap: 8px; }
            .kpis { grid-template-columns: repeat(2, 1fr); }
            .grid2 { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; max-width: none; }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">{{ __('Download PDF') }}</button>
        <a class="ghost" href="{{ route('insights', $filters['query']) }}">{{ __('Back to dashboard') }}</a>
        <a class="ghost" href="{{ route('insights.report', $filters['query'] + ['lang' => $isRtl ? 'en' : 'ar']) }}" lang="{{ $isRtl ? 'en' : 'ar' }}">{{ $isRtl ? 'English version' : 'النسخة العربية' }}</a>
        <p>{{ __('In the print window choose “Save as PDF”.') }}</p>
    </div>

    <main class="sheet">
        <header>
            <div>
                <h1>{{ __('QuoteIt insights report') }}</h1>
                <div class="muted">{{ __('Period') }}: <strong>{{ $periodText }}</strong> · {{ __('Topic') }}: <strong>{{ $filters['category']?->label() ?? __('All topics') }}</strong></div>
            </div>
            <div class="muted" style="text-align: end">{{ __('Generated') }}<br><strong>{{ $generatedAt->translatedFormat('j M Y · g:i A') }}</strong></div>
        </header>

        <section>
            <h2>{{ __('Key numbers') }}</h2>
            <div class="kpis">
                <div class="kpi"><div class="l">{{ __('Quotes') }}</div><div class="v">{{ $kpis['quotes'] }}</div><div class="e">{{ __('Quotes shared in the selected period and topic.') }}</div></div>
                <div class="kpi"><div class="l">{{ __('Likes') }}</div><div class="v">{{ $kpis['likes'] }}</div><div class="e">{{ __(':value per quote on average', ['value' => $kpis['avg_likes']]) }}</div></div>
                <div class="kpi"><div class="l">{{ __('Engagement rate') }}</div><div class="v">{{ $kpis['engagement'] }}%</div><div class="e">{{ __('Based on likes on the shared quotes') }}</div></div>
                <div class="kpi"><div class="l">{{ __('Comments') }}</div><div class="v">{{ $kpis['comments'] }}</div></div>
                <div class="kpi"><div class="l">{{ __('Saved to favourites') }}</div><div class="v">{{ $kpis['saves'] }}</div></div>
                <div class="kpi"><div class="l">{{ __('Shares') }}</div><div class="v">{{ $kpis['shares'] }}</div></div>
                <div class="kpi"><div class="l">{{ __('Users') }}</div><div class="v">{{ $kpis['users'] }}</div><div class="e">{{ trans_choice(':count active in this period|:count active in this period', $kpis['active_users'], ['count' => $kpis['active_users']]) }}</div></div>
            </div>
            <div class="formula">
                <strong>{{ __('How is the engagement rate calculated?') }}</strong>
                {{ __('It shows how much of what was shared in this period actually reached people: the more quotes that got at least one like, the higher the rate.') }}
                <ul style="margin: 4px 0 0">
                    <li>{{ __('Only the quotes shared in the selected period and topic.') }}</li>
                    <li>{{ __('A quote counts as “liked” if it got at least one like, no matter how many.') }}</li>
                    <li>{{ __('Comments, saves and shares are shown separately and are not part of this rate.') }}</li>
                </ul>
            </div>
        </section>

        @if ($findings)
            <section>
                <h2>{{ __('Key findings') }}</h2>
                <ul>
                    @foreach ($findings as $finding)
                        <li>{{ $finding }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section>
            <h2>{{ __('Most liked quotes') }}</h2>
            <table>
                <thead><tr><th>#</th><th>{{ __('Quote') }}</th><th>{{ __('Topic') }}</th><th>{{ __('Shared by') }}</th><th class="num">{{ __('Likes') }}</th><th class="num">{{ __('Comments') }}</th></tr></thead>
                <tbody>
                    @forelse ($topQuotes as $i => $q)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td dir="auto">{{ $q->content }} @if ($q->author)<span class="muted">— {{ $q->author }}</span>@endif</td>
                            <td>{{ $q->category?->label() ?? '—' }}</td>
                            <td>{{ $q->user->name }}</td>
                            <td class="num">{{ $q->likes_count }}</td>
                            <td class="num">{{ $q->comments_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ __('No quotes in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section>
            <h2>{{ __('Most popular topics') }}</h2>
            <table>
                <thead><tr><th>{{ __('Topic') }}</th><th class="num">{{ __('Quotes') }}</th><th class="num">{{ __('Likes') }}</th><th class="num">{{ __('Likes per quote') }}</th><th>{{ __('Share of quotes') }}</th></tr></thead>
                <tbody>
                    @foreach ($byCategory as $c)
                        <tr>
                            <td>{{ $c['name'] }}</td>
                            <td class="num">{{ $c['quotes'] }}</td>
                            <td class="num">{{ $c['likes'] }}</td>
                            <td class="num">{{ $c['quotes'] ? round($c['likes'] / $c['quotes'], 1) : '—' }}</td>
                            <td><div class="bar"><span style="width: {{ $c['quotes'] / $catMax * 100 }}%"></span></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2>{{ __('What users are interested in') }}</h2>
            <p class="muted" style="margin: 0 0 6px">{{ __('Topics people chose as interests, compared with the topics of the quotes they actually like.') }}</p>
            <table>
                <thead><tr><th>{{ __('Topic') }}</th><th class="num">{{ __('Chosen as interest') }}</th><th></th><th class="num">{{ __('Likes on this topic') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($interests as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ $row['chosen'] }}</td>
                            <td><div class="bar"><span style="width: {{ $row['chosen'] / $interestMax * 100 }}%"></span></div></td>
                            <td class="num">{{ $row['liked'] }}</td>
                            <td><div class="bar alt"><span style="width: {{ $row['liked'] / $interestMax * 100 }}%"></span></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="grid2">
            <div>
                <h2>{{ __('Feelings in Quote That Gets Me') }}</h2>
                <table>
                    @foreach ($feelings['moods'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td><div class="bar alt"><span style="width: {{ $row['total'] / $moodMax * 100 }}%"></span></div></td></tr>
                    @endforeach
                </table>
            </div>
            <div>
                <h2>{{ __('What people needed') }}</h2>
                <table>
                    @foreach ($feelings['goals'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td><div class="bar"><span style="width: {{ $row['total'] / $goalMax * 100 }}%"></span></div></td></tr>
                    @endforeach
                </table>
            </div>
        </section>

        <section class="grid2">
            <div>
                <h2>{{ __('Quote language') }}</h2>
                <p>{{ __('Arabic') }}: <strong>{{ $languages['Arabic'] }}</strong> ({{ $arPct }}%) · {{ __('English') }}: <strong>{{ $languages['English'] }}</strong> ({{ 100 - $arPct }}%)</p>
                <p class="muted">{{ __('Average length: :count characters', ['count' => $avgLength]) }}</p>
            </div>
            <div>
                <h2>{{ __('Top contributors') }}</h2>
                <table>
                    <thead><tr><th>{{ __('User') }}</th><th class="num">{{ __('Quotes') }}</th><th class="num">{{ __('Likes') }}</th></tr></thead>
                    @foreach ($topPeople as $person)
                        <tr><td>{{ $person->name }}</td><td class="num">{{ $person->quotes_count }}</td><td class="num">{{ $person->likes_received }}</td></tr>
                    @endforeach
                </table>
            </div>
        </section>

        <footer>
            {{ __('How the numbers are counted: quotes are counted by the date they were shared; likes, comments and saves by the date they happened. Times are in Riyadh time.') }}
            · QuoteIt · <span dir="ltr">&copy; {{ now()->year }} Wajd Alluhaibi</span>
        </footer>
    </main>

    @if (request('print'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>

</html>
