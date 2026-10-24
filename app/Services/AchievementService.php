<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Badges, daily quote challenge and points.
 */
class AchievementService
{
    /**
     * key => [stat, target, points]
     * The stat names come from stats() below.
     */
    public const BADGES = [
        'first_quote' => ['quotes', 1, 10],
        'ten_quotes' => ['quotes', 10, 50],
        'liked_10' => ['likes_received', 10, 30],
        'popular' => ['likes_received', 50, 100],
        'fan' => ['likes_given', 20, 20],
        'collector' => ['saves', 10, 20],
        'commenter' => ['comments', 10, 30],
        'social' => ['following', 5, 15],
        'followed' => ['followers', 5, 40],
        'gets_me' => ['picks', 5, 20],
        'designer' => ['designs', 3, 25],
        'challenger' => ['challenges', 3, 40],
        'challenge_week' => ['challenges', 7, 100],
    ];

    /** key => [what is counted today, target, points] */
    public const CHALLENGES = [
        'share_quote' => ['quotes', 1, 20],
        'like_3' => ['likes_given', 3, 15],
        'comment_1' => ['comments', 1, 15],
        'gets_me_1' => ['picks', 1, 15],
        'design_1' => ['designs', 1, 20],
        'follow_1' => ['following', 1, 10],
        'save_2' => ['saves', 2, 10],
    ];

    public static function badgeName(string $key): string
    {
        return match ($key) {
            'first_quote' => __('First words'),
            'ten_quotes' => __('Quote writer'),
            'liked_10' => __('Loved by 10'),
            'popular' => __('Crowd favourite'),
            'fan' => __('Big fan'),
            'collector' => __('Collector'),
            'commenter' => __('Conversation starter'),
            'social' => __('Social reader'),
            'followed' => __('Rising voice'),
            'gets_me' => __('Self-aware'),
            'designer' => __('Designer'),
            'challenger' => __('Challenger'),
            'challenge_week' => __('Challenge streak'),
            default => $key,
        };
    }

    public static function badgeDescription(string $key): string
    {
        [, $target] = self::BADGES[$key];

        return match ($key) {
            'first_quote', 'ten_quotes' => trans_choice('Share :count quote|Share :count quotes', $target, ['count' => $target]),
            'liked_10', 'popular' => __('Get :count likes on your quotes', ['count' => $target]),
            'fan' => __('Like :count quotes', ['count' => $target]),
            'collector' => __('Save :count quotes to favourites', ['count' => $target]),
            'commenter' => __('Write :count comments', ['count' => $target]),
            'social' => __('Follow :count people', ['count' => $target]),
            'followed' => __('Get :count followers', ['count' => $target]),
            'gets_me' => __('Pick :count quotes in Quote That Gets Me', ['count' => $target]),
            'designer' => __('Design :count quote images', ['count' => $target]),
            'challenger', 'challenge_week' => __('Complete :count daily challenges', ['count' => $target]),
            default => '',
        };
    }

    public static function challengeText(string $key): string
    {
        return match ($key) {
            'share_quote' => __('Share a quote today'),
            'like_3' => __('Like 3 quotes today'),
            'comment_1' => __('Comment on a quote today'),
            'gets_me_1' => __('Use Quote That Gets Me and pick a quote today'),
            'design_1' => __('Design a quote image today'),
            'follow_1' => __('Follow someone new today'),
            'save_2' => __('Save 2 quotes to your favourites today'),
            default => $key,
        };
    }

    /** Same challenge for everyone, changes every day. */
    public static function todaysChallenge(?Carbon $day = null): string
    {
        $keys = array_keys(self::CHALLENGES);
        $day ??= now();

        return $keys[crc32($day->toDateString()) % count($keys)];
    }

    /** @return array<string, int> */
    public function stats(User $user, ?Carbon $since = null): array
    {
        $since = $since?->toDateTimeString();
        $count = fn ($query, string $column = 'created_at') => $since ? $query->where($column, '>=', $since)->count() : $query->count();

        return [
            'quotes' => $count(DB::table('quotes')->where('user_id', $user->id)->where('published_at', '<=', now()), 'published_at'),
            'likes_received' => $count(DB::table('likes')->join('quotes', 'quotes.id', '=', 'likes.quote_id')->where('quotes.user_id', $user->id), 'likes.created_at'),
            'likes_given' => $count(DB::table('likes')->where('user_id', $user->id)),
            'saves' => $count(DB::table('bookmarks')->where('user_id', $user->id)),
            'comments' => $count(DB::table('comments')->where('user_id', $user->id)),
            'following' => $count(DB::table('follows')->where('follower_id', $user->id)),
            'followers' => $count(DB::table('follows')->where('following_id', $user->id)),
            'picks' => $count(DB::table('quote_picks')->where('user_id', $user->id)),
            'designs' => $count(DB::table('quote_designs')->where('user_id', $user->id)),
            'challenges' => $count(DB::table('challenge_completions')->where('user_id', $user->id)),
        ];
    }

    /**
     * Checks today's challenge and all badges, saves anything new.
     *
     * @return array<int, string> names of badges/challenges earned just now
     */
    public function sync(User $user): array
    {
        $new = [];

        // daily challenge
        $key = self::todaysChallenge();
        [$stat, $target, $points] = self::CHALLENGES[$key];
        $doneToday = DB::table('challenge_completions')->where('user_id', $user->id)->where('day', now()->toDateString())->exists();
        if (! $doneToday && $this->stats($user, now()->startOfDay())[$stat] >= $target) {
            DB::table('challenge_completions')->insertOrIgnore([
                'user_id' => $user->id, 'day' => now()->toDateString(), 'challenge' => $key,
                'points' => $points, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $new[] = __('Daily challenge done');
        }

        // badges
        $stats = $this->stats($user);
        $earned = DB::table('user_achievements')->where('user_id', $user->id)->pluck('badge')->all();
        foreach (self::BADGES as $badge => [$stat, $target, $points]) {
            if (! in_array($badge, $earned, true) && $stats[$stat] >= $target) {
                DB::table('user_achievements')->insertOrIgnore([
                    'user_id' => $user->id, 'badge' => $badge, 'points' => $points,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $new[] = self::badgeName($badge);
            }
        }

        return $new;
    }

    public function points(User $user): int
    {
        return (int) DB::table('user_achievements')->where('user_id', $user->id)->sum('points')
            + (int) DB::table('challenge_completions')->where('user_id', $user->id)->sum('points');
    }

    public static function level(int $points): int
    {
        return intdiv($points, 100) + 1;
    }

    /** @return Collection<int, array{key: string, name: string, description: string, points: int, earned_at: ?string}> */
    public function earnedBadges(User $user): Collection
    {
        return DB::table('user_achievements')->where('user_id', $user->id)->orderBy('created_at')->get()
            ->map(fn ($row) => [
                'key' => $row->badge,
                'name' => self::badgeName($row->badge),
                'description' => self::badgeDescription($row->badge),
                'points' => $row->points,
                'earned_at' => $row->created_at,
            ]);
    }

    /** Top users by points. */
    public function leaderboard(int $limit = 10): Collection
    {
        $badgePoints = DB::table('user_achievements')->select('user_id', DB::raw('sum(points) as pts'))->groupBy('user_id');
        $challengePoints = DB::table('challenge_completions')->select('user_id', DB::raw('sum(points) as pts'))->groupBy('user_id');

        $totals = collect()
            ->concat($badgePoints->get())
            ->concat($challengePoints->get())
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->sum('pts'))
            ->sortDesc()
            ->take($limit);

        $users = User::whereIn('id', $totals->keys())->get()->keyBy('id');

        return $totals->map(fn ($pts, $id) => ['user' => $users[$id], 'points' => (int) $pts])->values();
    }

    /**
     * Most active people in the last 7 days (quotes x3, comments x2, likes x1).
     */
    public function mostActiveThisWeek(int $limit = 3): Collection
    {
        $since = now()->subDays(7);
        $scores = collect();
        foreach ([['quotes', 3], ['comments', 2], ['likes', 1]] as [$table, $weight]) {
            DB::table($table)->where('created_at', '>=', $since)
                ->select('user_id', DB::raw('count(*) as total'))->groupBy('user_id')->get()
                ->each(fn ($row) => $scores[$row->user_id] = ($scores[$row->user_id] ?? 0) + $row->total * $weight);
        }

        $top = $scores->sortDesc()->take($limit);
        $users = User::whereIn('id', $top->keys())->get()->keyBy('id');

        return $top->map(fn ($score, $id) => ['user' => $users[$id], 'score' => $score])->values();
    }
}
