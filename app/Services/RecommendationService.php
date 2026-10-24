<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Goal;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Learns what a user likes from the quotes they like, save and pick,
 * then suggests quotes they haven't seen, with a short reason for each.
 */
class RecommendationService
{
    /**
     * @return array{categories: Collection, authors: Collection, goals: Collection, following: Collection, interests: Collection, signals: int}
     */
    public function tasteProfile(User $user): array
    {
        // saved quotes count double, they show stronger interest than a like
        $liked = DB::table('likes')->where('user_id', $user->id)->pluck('quote_id')->map(fn ($id) => [$id, 1]);
        $saved = DB::table('bookmarks')->where('user_id', $user->id)->pluck('quote_id')->map(fn ($id) => [$id, 2]);
        $picked = DB::table('quote_picks')->where('user_id', $user->id)->pluck('quote_id')->map(fn ($id) => [$id, 1]);
        $weights = $liked->concat($saved)->concat($picked)
            ->groupBy(fn ($row) => $row[0])
            ->map(fn ($rows) => $rows->sum(fn ($row) => $row[1]));

        $quotes = Quote::with('goals')->whereIn('id', $weights->keys())->get();

        $categories = collect();
        $authors = collect();
        $goals = collect();
        foreach ($quotes as $quote) {
            $w = $weights[$quote->id];
            if ($quote->category_id) {
                $categories[$quote->category_id] = ($categories[$quote->category_id] ?? 0) + $w;
            }
            if ($quote->author) {
                $authors[$quote->author] = ($authors[$quote->author] ?? 0) + $w;
            }
            foreach ($quote->goals as $goal) {
                $goals[$goal->id] = ($goals[$goal->id] ?? 0) + $w;
            }
        }

        return [
            'categories' => $categories->sortDesc(),
            'authors' => $authors->sortDesc(),
            'goals' => $goals->sortDesc(),
            'following' => DB::table('follows')->where('follower_id', $user->id)->pluck('following_id'),
            'interests' => $user->interests()->pluck('categories.id'),
            'signals' => $weights->count(),
        ];
    }

    /**
     * @return Collection<int, array{quote: Quote, reason: string, reasons: array<int, string>, extra: array<int, string>, score: float, personal: bool}>
     */
    public function recommend(User $user, int $limit = 6): Collection
    {
        $taste = $this->tasteProfile($user);

        $seen = DB::table('likes')->where('user_id', $user->id)->pluck('quote_id')
            ->concat(DB::table('bookmarks')->where('user_id', $user->id)->pluck('quote_id'));

        $candidates = Quote::withCardData()
            ->with('goals')
            ->where('user_id', '!=', $user->id)
            ->whereNotIn('id', $seen)
            ->latest()
            ->limit(400)
            ->get();

        // people with a similar taste: they liked at least one of the quotes I liked
        $myLikes = DB::table('likes')->where('user_id', $user->id)->pluck('quote_id');
        $similarPeople = $myLikes->isEmpty() ? collect() : DB::table('likes')
            ->whereIn('quote_id', $myLikes)->where('user_id', '!=', $user->id)->distinct()->pluck('user_id');
        $likedBySimilar = $similarPeople->isEmpty() ? collect() : DB::table('likes')
            ->whereIn('user_id', $similarPeople)->select('quote_id', DB::raw('count(*) as total'))->groupBy('quote_id')->pluck('total', 'quote_id');

        // liked a lot in the last 7 days
        $trending = DB::table('likes')->where('created_at', '>=', now()->subDays(7))
            ->select('quote_id', DB::raw('count(*) as total'))->groupBy('quote_id')->pluck('total', 'quote_id');

        $savedAuthors = Quote::whereIn('id', DB::table('bookmarks')->where('user_id', $user->id)->pluck('quote_id'))
            ->whereNotNull('author')->pluck('author')->unique();

        $categoryNames = Category::all()->keyBy('id');
        $goalNames = Goal::all()->keyBy('id');
        $followedNames = User::whereIn('id', $taste['following'])->pluck('name', 'id');

        $scored = $candidates->map(function (Quote $quote) use ($taste, $categoryNames, $goalNames, $followedNames, $likedBySimilar, $trending, $savedAuthors) {
            $reasons = [];

            if (isset($likedBySimilar[$quote->id])) {
                $reasons[] = [$likedBySimilar[$quote->id] * 2.5, __('Recommended because people with a taste like yours liked it'), __('people with a taste like yours liked it')];
            }
            if ($quote->author && $savedAuthors->contains($quote->author)) {
                $reasons[] = [3, __('Recommended because you saved another quote by :author', ['author' => $quote->author]), __('you saved another quote by :author', ['author' => $quote->author])];
            }
            if ($quote->category_id && isset($taste['categories'][$quote->category_id]) && $quote->published_at?->gt(now()->subDays(3))) {
                $reasons[] = [$taste['categories'][$quote->category_id] * 1.5,
                    __('New in :category, a topic you like', ['category' => $categoryNames[$quote->category_id]->label()]),
                    __('new in :category, a topic you like', ['category' => $categoryNames[$quote->category_id]->label()])];
            }

            if ($quote->category_id && isset($taste['categories'][$quote->category_id])) {
                $reasons[] = [$taste['categories'][$quote->category_id] * 3,
                    __('Recommended because you like :category quotes', ['category' => $categoryNames[$quote->category_id]->label()]),
                    __('you like :category quotes', ['category' => $categoryNames[$quote->category_id]->label()])];
            }
            if ($quote->author && isset($taste['authors'][$quote->author])) {
                $reasons[] = [$taste['authors'][$quote->author] * 2.5,
                    __('Recommended because you liked quotes by :author', ['author' => $quote->author]),
                    __('you liked quotes by :author', ['author' => $quote->author])];
            }
            foreach ($quote->goals as $goal) {
                if (isset($taste['goals'][$goal->id])) {
                    $reasons[] = [$taste['goals'][$goal->id] * 1.5,
                        __('Recommended because you often keep quotes about :goal', ['goal' => mb_strtolower($goalNames[$goal->id]->label())]),
                        __('you often keep quotes about :goal', ['goal' => mb_strtolower($goalNames[$goal->id]->label())])];
                }
            }
            if ($taste['following']->contains($quote->user_id)) {
                $reasons[] = [3, __('Recommended because you follow :name', ['name' => $followedNames[$quote->user_id] ?? '']),
                    __('you follow :name', ['name' => $followedNames[$quote->user_id] ?? ''])];
            }
            if ($quote->category_id && $taste['interests']->contains($quote->category_id)) {
                $reasons[] = [2, __(':category is one of your interests', ['category' => $categoryNames[$quote->category_id]->label()]),
                    __(':category is one of your interests', ['category' => $categoryNames[$quote->category_id]->label()])];
            }

            usort($reasons, fn ($a, $b) => $b[0] <=> $a[0]);
            $score = array_sum(array_column($reasons, 0)) + $quote->likes_count * 0.2 + ($trending[$quote->id] ?? 0) * 0.5;

            $fallback = ($trending[$quote->id] ?? 0) >= 2
                ? __('Trending this week: liked :count times in 7 days', ['count' => $trending[$quote->id]])
                : __('Popular with the QuoteIt community');

            return [
                'quote' => $quote,
                'score' => $score,
                'reason' => $reasons[0][1] ?? $fallback,
                'reasons' => array_column($reasons, 1),
                // short wording for the extra reasons under the main one
                'extra' => array_slice(array_column($reasons, 2), 1, 2),
                'personal' => ! empty($reasons),
            ];
        });

        // personal matches first; if there are not enough, fill with popular quotes
        return $scored
            ->sortByDesc(fn ($row) => [$row['personal'] ? 1 : 0, $row['score']])
            ->take($limit)
            ->values();
    }
}
