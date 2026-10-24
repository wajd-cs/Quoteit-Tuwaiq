<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A short summary of the last 7 days for a user's own quotes,
 * compared with the 7 days before.
 */
class WeeklySummary
{
    /**
     * @return array{likes: int, likes_before: int, comments: int, shares: int, new_quotes: int, top_quote: ?Quote, top_score: int, top_topic: ?string, top_topic_count: int}
     */
    public function for(User $user): array
    {
        $weekStart = now()->subDays(7);
        $previousStart = now()->subDays(14);
        $myQuotes = DB::table('quotes')->where('user_id', $user->id)->where('published_at', '<=', now())->pluck('id');

        $countOn = fn (string $table, Carbon $from, ?Carbon $to = null) => DB::table($table)
            ->whereIn('quote_id', $myQuotes)
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<', $to))
            ->count();

        // the quote people interacted with most this week
        $scores = collect(['likes', 'comments', 'quote_shares'])
            ->flatMap(fn (string $table) => DB::table($table)
                ->whereIn('quote_id', $myQuotes)
                ->where('created_at', '>=', $weekStart)
                ->pluck('quote_id'))
            ->countBy()
            ->sortDesc();

        $topId = $scores->keys()->first();

        // the topic this user writes about the most
        $topic = DB::table('quotes')
            ->where('user_id', $user->id)
            ->where('published_at', '<=', now())
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('count(*) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->first();

        return [
            'likes' => $countOn('likes', $weekStart),
            'likes_before' => $countOn('likes', $previousStart, $weekStart),
            'comments' => $countOn('comments', $weekStart),
            'shares' => $countOn('quote_shares', $weekStart),
            'new_quotes' => DB::table('quotes')->where('user_id', $user->id)
                ->whereBetween('published_at', [$weekStart, now()])->count(),
            'top_quote' => $topId ? Quote::find($topId) : null,
            'top_score' => (int) $scores->first(),
            'top_topic' => $topic ? Category::find($topic->category_id)?->label() : null,
            'top_topic_count' => $topic->total ?? 0,
        ];
    }
}
