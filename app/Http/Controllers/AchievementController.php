<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    public function index(Request $request, AchievementService $achievements)
    {
        $user = $request->user();
        $this->rewardBadges($request);

        $stats = $achievements->stats($user);
        $earned = $achievements->earnedBadges($user)->keyBy('key');
        $points = $achievements->points($user);

        $badges = collect(AchievementService::BADGES)->map(fn ($def, $key) => [
            'key' => $key,
            'name' => AchievementService::badgeName($key),
            'description' => AchievementService::badgeDescription($key),
            'points' => $def[2],
            'progress' => min($stats[$def[0]], $def[1]),
            'target' => $def[1],
            'earned_at' => $earned[$key]['earned_at'] ?? null,
        ])->sortByDesc(fn ($b) => [$b['earned_at'] ? 1 : 0, $b['progress'] / $b['target']])->values();

        $challengeKey = AchievementService::todaysChallenge();
        [$stat, $target, $challengePoints] = AchievementService::CHALLENGES[$challengeKey];
        $todayStats = $achievements->stats($user, now()->startOfDay());

        return view('achievements.index', [
            'page' => 'Achievements',
            'points' => $points,
            'level' => AchievementService::level($points),
            'nextLevelAt' => AchievementService::level($points) * 100,
            'badges' => $badges,
            'challenge' => [
                'text' => AchievementService::challengeText($challengeKey),
                'progress' => min($todayStats[$stat], $target),
                'target' => $target,
                'points' => $challengePoints,
                'done' => DB::table('challenge_completions')->where('user_id', $user->id)->where('day', now()->toDateString())->exists(),
            ],
            'challengeDays' => DB::table('challenge_completions')->where('user_id', $user->id)->count(),
            'leaderboard' => $achievements->leaderboard(10),
            'mostActive' => $achievements->mostActiveThisWeek(3),
        ]);
    }
}
