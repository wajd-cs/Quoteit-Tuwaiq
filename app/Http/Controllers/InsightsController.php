<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InsightsController extends Controller
{
    private const RANGES = ['7', '30', '90', 'all', 'custom'];

    public function index(Request $request)
    {
        return view('insights.index', ['page' => 'Insights'] + $this->data($this->filters($request)));
    }

    /**
     * Printable A4 report. The browser saves it as a PDF ("Save as PDF"), which keeps Arabic text correct.
     */
    public function report(Request $request)
    {
        // the report can be made in Arabic or English, whatever the site language is
        $lang = $request->query('lang');
        if (in_array($lang, SetLocale::SUPPORTED, true)) {
            app()->setLocale($lang);
            Carbon::setLocale($lang);
        }

        $filters = $this->filters($request);

        return view('insights.report', ['page' => 'Report'] + $this->data($filters) + [
            'interests' => $this->interestsAnalysis(),
            'feelings' => $this->feelingsAnalysis($filters),
            'generatedAt' => now(),
        ]);
    }

    /**
     * Quotes as a CSV file (opens in Excel / Power BI). Uses the same filters as the dashboard.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        $quotes = $this->scopedQuotes($filters)
            ->with(['user', 'category'])
            ->withCount(['likers as likes', 'comments', 'bookmarkers as saves'])
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($quotes) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads Arabic
            fputcsv($out, ['id', 'quote', 'author', 'category', 'shared_by', 'published_at', 'likes', 'comments', 'saves', 'length', 'language']);

            foreach ($quotes as $quote) {
                fputcsv($out, [
                    $quote->id,
                    $quote->content,
                    $quote->author,
                    $quote->category?->name,
                    $quote->user->name,
                    $quote->published_at->toDateTimeString(),
                    $quote->likes,
                    $quote->comments_count,
                    $quote->saves,
                    mb_strlen($quote->content),
                    $this->isArabic($quote->content) ? 'Arabic' : 'English',
                ]);
            }

            fclose($out);
        }, 'quoteit-data-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{range: string, from: ?Carbon, to: Carbon, category: ?Category, query: array<string, string>}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:'.implode(',', self::RANGES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
        ]);

        $range = $validated['range'] ?? (isset($validated['from']) ? 'custom' : 'all');
        $to = now()->endOfDay();
        $from = null;

        if ($range === 'custom') {
            $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
            $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : $to;
        } elseif ($range !== 'all') {
            $from = now()->subDays((int) $range - 1)->startOfDay();
        }

        $category = isset($validated['category']) ? Category::firstWhere('slug', $validated['category']) : null;

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'category' => $category,
            'query' => array_filter([
                'range' => $range === 'all' ? null : $range,
                'from' => $range === 'custom' ? $from?->toDateString() : null,
                'to' => $range === 'custom' ? $to->toDateString() : null,
                'category' => $category?->slug,
            ]),
        ];
    }

    private function scopedQuotes(array $f)
    {
        return Quote::query()
            ->when($f['from'], fn ($q) => $q->where('quotes.published_at', '>=', $f['from']))
            ->where('quotes.published_at', '<=', $f['to'])
            ->when($f['category'], fn ($q) => $q->where('quotes.category_id', $f['category']->id));
    }

    /** likes / comments / bookmarks made in the date range, on quotes in the chosen category */
    private function scopedActivity(string $table, array $f)
    {
        return DB::table($table)
            ->join('quotes', 'quotes.id', '=', "{$table}.quote_id")
            ->when($f['from'], fn ($q) => $q->where("{$table}.created_at", '>=', $f['from']))
            ->where("{$table}.created_at", '<=', $f['to'])
            ->when($f['category'], fn ($q) => $q->where('quotes.category_id', $f['category']->id));
    }

    private function data(array $f): array
    {
        $quoteIds = $this->scopedQuotes($f)->pluck('quotes.id');
        $totalQuotes = $quoteIds->count();
        $likesOnScoped = DB::table('likes')->whereIn('quote_id', $quoteIds)->count();
        $likedQuotes = DB::table('likes')->whereIn('quote_id', $quoteIds)->distinct()->count('quote_id');

        $activeUsers = $this->scopedQuotes($f)->pluck('user_id')
            ->merge($this->scopedActivity('likes', $f)->pluck('likes.user_id'))
            ->merge($this->scopedActivity('comments', $f)->pluck('comments.user_id'))
            ->unique()->count();

        $kpis = [
            'quotes' => $totalQuotes,
            'users' => User::count(),
            'likes' => $this->scopedActivity('likes', $f)->count(),
            'comments' => $this->scopedActivity('comments', $f)->count(),
            'saves' => $this->scopedActivity('bookmarks', $f)->count(),
            'shares' => $this->scopedActivity('quote_shares', $f)->count(),
            'avg_likes' => $totalQuotes ? round($likesOnScoped / $totalQuotes, 1) : 0,
            'engagement' => $totalQuotes ? (int) round($likedQuotes / $totalQuotes * 100) : 0,
            'liked_quotes' => $likedQuotes,
            'active_users' => $activeUsers,
        ];

        $trendDays = $f['range'] === 'all' ? 14 : min(92, max(1, (int) $f['from']?->diffInDays($f['to']) + 1));
        $trendFrom = $f['range'] === 'all' ? now()->subDays(13)->startOfDay() : $f['from']->copy();

        $contents = $this->scopedQuotes($f)->pluck('content');
        $byCategory = $this->quotesByCategory($f);
        $hours = $this->activityByHour($f);
        $languages = $this->languageSplit($contents);
        $avgLength = (int) round($contents->avg(fn ($c) => mb_strlen($c)) ?? 0);

        return [
            'filters' => $f,
            'findings' => $this->findings($kpis, $byCategory, $hours, $languages, $avgLength),
            'kpis' => $kpis,
            'byCategory' => $byCategory,
            'trend' => $this->dailyTrend($f, $trendFrom, $trendDays),
            'hours' => $hours,
            'peakHour' => $hours->max() > 0 ? $hours->search($hours->max()) : null,
            'languages' => $languages,
            'avgLength' => $avgLength,
            'topQuotes' => Quote::withCardData()->whereIn('id', $quoteIds)->orderByDesc('likes_count')->latest()->limit(5)->get(),
            'topPeople' => $this->topContributors($f),
        ];
    }

    /**
     * Short sentences that explain the numbers.
     *
     * @return array<int, string>
     */
    private function findings(array $kpis, Collection $byCategory, Collection $hours, array $languages, int $avgLength): array
    {
        $findings = [];
        $top = $byCategory->first();
        if ($top && $top['quotes'] > 0) {
            $findings[] = __(':category is the most shared topic (:count quotes, :percent% of all quotes).', [
                'category' => $top['name'], 'count' => $top['quotes'], 'percent' => round($top['quotes'] / max(1, $kpis['quotes']) * 100),
            ]);
        }
        $best = $byCategory->filter(fn ($c) => $c['quotes'] > 0)->sortByDesc(fn ($c) => $c['likes'] / $c['quotes'])->first();
        if ($best && $best['likes'] > 0) {
            $findings[] = __(':category quotes get the most likes per quote (:value).', ['category' => $best['name'], 'value' => round($best['likes'] / $best['quotes'], 1)]);
        }
        if ($hours->max() > 0) {
            $findings[] = __('People are most active around :hour (Riyadh time).', ['hour' => Carbon::createFromTime($hours->search($hours->max()))->translatedFormat('g A')]);
        }
        $total = max(1, array_sum($languages));
        if (array_sum($languages) > 0) {
            $ar = (int) round($languages['Arabic'] / $total * 100);
            $findings[] = __(':ar% of quotes are in Arabic and :en% in English. An average quote is :length characters long.', ['ar' => $ar, 'en' => 100 - $ar, 'length' => $avgLength]);
        }
        if ($kpis['quotes'] > 0) {
            $findings[] = __('The engagement rate is based on how many of the quotes shared in this period received at least one like.');
        }

        return $findings;
    }

    private function quotesByCategory(array $f): Collection
    {
        $quotes = $this->scopedQuotes($f)->select('category_id', DB::raw('count(*) as total'))->groupBy('category_id')->pluck('total', 'category_id');
        $likes = $this->scopedActivity('likes', $f)->select('quotes.category_id', DB::raw('count(*) as total'))->groupBy('quotes.category_id')->pluck('total', 'category_id');

        return Category::orderBy('name')->get()
            ->when($f['category'], fn ($c) => $c->where('id', $f['category']->id))
            ->map(fn ($c) => [
                'name' => $c->label(),
                'slug' => $c->slug,
                'quotes' => (int) ($quotes[$c->id] ?? 0),
                'likes' => (int) ($likes[$c->id] ?? 0),
            ])
            ->sortByDesc('quotes')
            ->values();
    }

    /**
     * New quotes and likes per day (days with no activity count as 0).
     */
    private function dailyTrend(array $f, Carbon $from, int $days): Collection
    {
        $quotes = $this->scopedQuotes($f)->where('quotes.published_at', '>=', $from)->pluck('quotes.published_at')
            ->countBy(fn ($d) => Carbon::parse($d)->toDateString());
        $likes = $this->scopedActivity('likes', $f)->where('likes.created_at', '>=', $from)->pluck('likes.created_at')
            ->countBy(fn ($d) => Carbon::parse($d)->toDateString());

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $quotes, $likes) {
            $day = $from->copy()->addDays($i)->toDateString();

            return ['date' => $day, 'quotes' => $quotes[$day] ?? 0, 'likes' => $likes[$day] ?? 0];
        });
    }

    /**
     * When people are most active: quotes + likes + comments by hour of the day (Riyadh time).
     */
    private function activityByHour(array $f): Collection
    {
        $times = $this->scopedQuotes($f)->pluck('quotes.published_at')
            ->merge($this->scopedActivity('likes', $f)->pluck('likes.created_at'))
            ->merge($this->scopedActivity('comments', $f)->pluck('comments.created_at'))
            ->filter();

        $counts = $times->countBy(fn ($t) => (int) Carbon::parse($t)->timezone('Asia/Riyadh')->format('G'));

        return collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $counts[$h] ?? 0]);
    }

    private function languageSplit(Collection $contents): array
    {
        $arabic = $contents->filter(fn ($c) => $this->isArabic($c))->count();

        return ['Arabic' => $arabic, 'English' => $contents->count() - $arabic];
    }

    private function topContributors(array $f): Collection
    {
        $quoteCounts = $this->scopedQuotes($f)->select('user_id', DB::raw('count(*) as total'))->groupBy('user_id')->pluck('total', 'user_id');
        $likesReceived = $this->scopedActivity('likes', $f)->select('quotes.user_id', DB::raw('count(*) as total'))->groupBy('quotes.user_id')->pluck('total', 'user_id');

        $top = $quoteCounts->sortDesc()->take(5);

        return User::whereIn('id', $top->keys())->get()
            ->each(function ($u) use ($quoteCounts, $likesReceived) {
                $u->quotes_count = (int) $quoteCounts[$u->id];
                $u->likes_received = (int) ($likesReceived[$u->id] ?? 0);
            })
            ->sortByDesc('quotes_count')
            ->values();
    }

    /** Interests people chose + categories of the quotes they like. */
    private function interestsAnalysis(): Collection
    {
        $chosen = DB::table('category_user')->select('category_id', DB::raw('count(*) as total'))->groupBy('category_id')->pluck('total', 'category_id');
        $liked = DB::table('likes')->join('quotes', 'quotes.id', '=', 'likes.quote_id')->whereNotNull('quotes.category_id')
            ->select('quotes.category_id', DB::raw('count(*) as total'))->groupBy('quotes.category_id')->pluck('total', 'category_id');

        return Category::orderBy('name')->get()->map(fn ($c) => [
            'name' => $c->label(),
            'chosen' => (int) ($chosen[$c->id] ?? 0),
            'liked' => (int) ($liked[$c->id] ?? 0),
        ])->sortByDesc(fn ($r) => $r['chosen'] + $r['liked'])->values();
    }

    /** Feelings and goals picked in "Quote That Gets Me" during the range. */
    private function feelingsAnalysis(array $f): array
    {
        $picks = DB::table('quote_picks')
            ->when($f['from'], fn ($q) => $q->where('created_at', '>=', $f['from']))
            ->where('created_at', '<=', $f['to']);

        $moods = (clone $picks)->select('mood_id', DB::raw('count(*) as total'))->groupBy('mood_id')->pluck('total', 'mood_id');
        $goals = (clone $picks)->select('goal_id', DB::raw('count(*) as total'))->groupBy('goal_id')->pluck('total', 'goal_id');

        return [
            'moods' => Mood::orderBy('sort')->get()->map(fn ($m) => ['name' => $m->label(), 'total' => (int) ($moods[$m->id] ?? 0)])->sortByDesc('total')->values(),
            'goals' => Goal::orderBy('sort')->get()->map(fn ($g) => ['name' => $g->label(), 'total' => (int) ($goals[$g->id] ?? 0)])->sortByDesc('total')->values(),
        ];
    }

    private function isArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }
}
