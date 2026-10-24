<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Models\Category;
use App\Models\Quote;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all');
        $search = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), Quote::SORTS, true) ? $request->query('sort') : 'latest';
        $category = $request->filled('category')
            ? Category::where('slug', $request->query('category'))->first()
            : null;

        // for-you and following need login
        if (in_array($tab, ['for-you', 'following']) && Auth::guest()) {
            return redirect()->route('login')->with('status', __('Log in to see quotes picked for you.'));
        }

        $query = Quote::query()->withCardData()->search($search);

        if ($category) {
            $query->where('category_id', $category->id);
        }

        $needsSetup = false;

        if ($tab === 'for-you') {
            $user = Auth::user();
            $interestIds = $user->interests()->pluck('categories.id');
            $followingIds = $user->following()->pluck('users.id');
            $needsSetup = $interestIds->isEmpty() && $followingIds->isEmpty();

            $query->where('user_id', '!=', $user->id)
                ->where(function ($q) use ($interestIds, $followingIds) {
                    $q->whereIn('category_id', $interestIds)->orWhereIn('user_id', $followingIds);
                });
        } elseif ($tab === 'following') {
            $query->whereIn('user_id', Auth::user()->following()->pluck('users.id'));
        } else {
            $tab = 'all';
        }

        $query->sortBy($sort);

        $quotes = $query->paginate(12)->withQueryString();

        // show the hero only on the normal home page
        $isPlainView = $tab === 'all' && ! $category && $search === '' && $quotes->onFirstPage();

        return view('welcome', [
            'page' => 'Home',
            'quotes' => $quotes,
            'tab' => $tab,
            'search' => $search,
            'sort' => $sort,
            'activeCategory' => $category,
            'needsSetup' => $needsSetup,
            'quoteOfTheDay' => $isPlainView ? $this->quoteOfTheDay() : null,
            'recommended' => $isPlainView && Auth::check() ? app(RecommendationService::class)->recommend(Auth::user(), 3) : collect(),
            'showHero' => $isPlainView,
        ]);
    }

    public function show(Quote $quote)
    {
        $quote = Quote::withoutGlobalScope('published')->withCardData()->findOrFail($quote->id);
        $comments = $quote->comments()->with('user')->latest()->get();

        return view('quotes.show', [
            'page' => 'Quote',
            'quote' => $quote,
            'comments' => $comments,
            'similar' => $this->similarTo($quote),
        ]);
    }

    public function store(QuoteRequest $request)
    {
        $quote = Auth::user()->quotes()->create($request->safe()->only(['content', 'author', 'category_id']) + [
            'published_at' => $request->publishAt() ?? now(),
        ]);
        $this->syncFeelings($quote, $request);

        if ($quote->isScheduled()) {
            return redirect()->route('users.show', [Auth::user(), 'tab' => 'scheduled'])
                ->with('status', __('Your quote is scheduled. It will be published on :date.', ['date' => $quote->published_at->translatedFormat('j M Y · g:i A')]));
        }

        $this->rewardBadges($request);

        return redirect()->route('home')
            ->with('status', __('Your quote is live!'))
            ->withFragment('quote-'.$quote->id);
    }

    public function update(QuoteRequest $request, Quote $quote)
    {
        abort_unless($quote->isOwnedBy(), 403);

        $data = $request->safe()->only(['content', 'author', 'category_id']);
        // the time can only change while the quote is still waiting
        if ($quote->isScheduled() && $request->has('schedule_section')) {
            $data['published_at'] = $request->publishAt() ?? now();
        }
        $quote->update($data);
        $this->syncFeelings($quote, $request);

        return redirect()->to(url()->previous(route('home')).'#quote-'.$quote->id)
            ->with('status', __('Quote updated.'));
    }

    /** Publish a scheduled quote right away. */
    public function publishNow(Request $request, Quote $quote)
    {
        abort_unless($quote->isOwnedBy(), 403);

        if ($quote->isScheduled()) {
            $quote->update(['published_at' => now()]);
            $this->rewardBadges($request);
        }

        return redirect()->route('quotes.show', $quote)->with('status', __('Your quote is live!'));
    }

    public function destroy(Quote $quote)
    {
        abort_unless($quote->isOwnedBy(), 403);

        $quote->delete();

        // can't go back to a deleted quote page
        $back = url()->previous();
        $target = str_contains($back, '/quotes/'.$quote->id) ? route('home') : $back;

        return redirect()->to($target)->with('status', __('Quote deleted.'));
    }

    // moods and goals used by "Quote That Gets Me"
    private function syncFeelings(Quote $quote, QuoteRequest $request): void
    {
        // only when the form sent this section, so other updates don't remove the tags
        if (! $request->has('feelings_section')) {
            return;
        }

        $quote->moods()->sync($request->validated('moods', []));
        $quote->goals()->sync($request->validated('goals', []));
    }

    private function quoteOfTheDay(): ?Quote
    {
        $today = now()->toDateString();

        $id = Cache::remember('quote-of-the-day:'.$today, now()->endOfDay(), function () use ($today) {
            // pick from liked quotes if there are any
            $ids = Quote::has('likers')->orderBy('id')->pluck('id');
            if ($ids->isEmpty()) {
                $ids = Quote::orderBy('id')->pluck('id');
            }

            return $ids->isEmpty() ? null : $ids[crc32($today) % $ids->count()];
        });

        return $id ? Quote::withCardData()->find($id) : null;
    }

    private function similarTo(Quote $quote)
    {
        $similar = Quote::withCardData()
            ->whereKeyNot($quote->id)
            ->where(function ($q) use ($quote) {
                $q->when($quote->category_id, fn ($q) => $q->where('category_id', $quote->category_id))
                    ->when($quote->author, fn ($q) => $q->orWhere('author', $quote->author));
            })
            ->orderByDesc('likes_count')
            ->latest()
            ->limit(4)
            ->get();

        if ($similar->count() < 4) {
            $more = Quote::withCardData()
                ->whereKeyNot($quote->id)
                ->whereNotIn('id', $similar->pluck('id'))
                ->where('user_id', $quote->user_id)
                ->latest()
                ->limit(4 - $similar->count())
                ->get();
            $similar = $similar->concat($more);
        }

        return $similar;
    }
}
