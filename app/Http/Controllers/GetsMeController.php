<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuotePickRequest;
use App\Http\Requests\SuggestQuoteRequest;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use App\Models\QuotePick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Quote That Gets Me (اقتباسي يفهمني): pick how you feel and what you need, get quotes that fit.
 */
class GetsMeController extends Controller
{
    private const RESULTS = 3;

    public function index()
    {
        return view('gets-me.index', $this->formData() + [
            'page' => 'Gets me',
            'quotes' => null,
            'selectedMood' => null,
            'selectedGoal' => null,
            'goalCategoryIds' => [],
            'recentPicks' => $this->recentPicks(),
        ]);
    }

    public function suggest(SuggestQuoteRequest $request)
    {
        $mood = Mood::where('slug', $request->validated('mood'))->firstOrFail();
        $goal = Goal::where('slug', $request->validated('goal'))->firstOrFail();
        $except = $request->validated('except', []);

        $quotes = $this->suggestions($mood, $goal, $except);

        // the user has seen every match already, so start again from the top
        if ($quotes->isEmpty() && $except) {
            $quotes = $this->suggestions($mood, $goal, []);
        }

        $goalCategoryIds = $goal->categories()->pluck('categories.id')->all();

        if ($request->expectsJson()) {
            return response()->json([
                'count' => $quotes->count(),
                'ids' => $quotes->pluck('id'),
                'html' => view('gets-me.partials.results', compact('quotes', 'mood', 'goal', 'goalCategoryIds'))->render(),
            ]);
        }

        return view('gets-me.index', $this->formData() + [
            'page' => 'Gets me',
            'quotes' => $quotes,
            'selectedMood' => $mood,
            'selectedGoal' => $goal,
            'goalCategoryIds' => $goalCategoryIds,
            'recentPicks' => $this->recentPicks(),
        ]);
    }

    /** "This gets me": save the quote to the user's history. */
    public function pick(QuotePickRequest $request)
    {
        $mood = Mood::where('slug', $request->validated('mood'))->firstOrFail();
        $goal = Goal::where('slug', $request->validated('goal'))->firstOrFail();

        // pressing twice on the same day doesn't add a second row
        $pick = $request->user()->quotePicks()
            ->where('quote_id', $request->validated('quote_id'))
            ->where('mood_id', $mood->id)
            ->where('goal_id', $goal->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->first()
            ?? $request->user()->quotePicks()->create([
                'quote_id' => $request->validated('quote_id'),
                'mood_id' => $mood->id,
                'goal_id' => $goal->id,
            ]);

        $badges = $this->rewardBadges($request);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'id' => $pick->id, 'badges' => $badges], $pick->wasRecentlyCreated ? 201 : 200);
        }

        return redirect()->route('gets-me.history')->with('status', __('Added to your history.'));
    }

    public function history(Request $request)
    {
        $picks = $request->user()->quotePicks()
            ->with(['mood', 'goal', 'quote' => fn ($q) => $q->withCardData()])
            ->when($request->filled('mood'), fn ($q) => $q->whereHas('mood', fn ($m) => $m->where('slug', $request->query('mood'))))
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $moodCounts = $request->user()->quotePicks()
            ->selectRaw('mood_id, count(*) as total')
            ->groupBy('mood_id')
            ->pluck('total', 'mood_id');

        return view('gets-me.history', $this->formData() + [
            'page' => 'Gets me',
            'picks' => $picks,
            'moodCounts' => $moodCounts,
            'activeMood' => $request->query('mood'),
        ]);
    }

    public function destroy(QuotePick $pick)
    {
        abort_unless($pick->user_id === Auth::id(), 403);

        $pick->delete();

        return redirect()->back()->with('status', __('Removed from your history.'));
    }

    private function suggestions(Mood $mood, Goal $goal, array $except)
    {
        return Quote::withCardData()
            ->forMoodAndGoal($mood, $goal)
            ->whereNotIn('quotes.id', $except)
            ->limit(self::RESULTS)
            ->get();
    }

    private function formData(): array
    {
        return [
            'moods' => Mood::orderBy('sort')->get(),
            'goals' => Goal::orderBy('sort')->get(),
        ];
    }

    private function recentPicks()
    {
        return Auth::user()->quotePicks()->with(['mood', 'goal', 'quote'])->latest()->orderByDesc('id')->limit(3)->get();
    }
}
