<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Checks badges / daily challenge after an action.
     * Normal requests get a flash message, JSON requests get the list back.
     *
     * @return array<int, string>
     */
    protected function rewardBadges(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        $new = app(AchievementService::class)->sync($request->user());

        if ($new && ! $request->expectsJson()) {
            $request->session()->flash('badges', $new);
        }

        return $new;
    }
}
