<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;

/**
 * Short sentence telling the user why "Quote That Gets Me" picked a quote.
 */
class GetsMeExplainer
{
    /** @param  array<int>  $goalCategoryIds */
    public static function explain(Quote $quote, Mood $mood, Goal $goal, array $goalCategoryIds): string
    {
        $moodName = mb_strtolower($mood->label());
        $goalName = mb_strtolower($goal->label());
        $inGoalCategory = $quote->category_id && in_array($quote->category_id, $goalCategoryIds, true);

        if ($quote->goal_match && $quote->mood_match) {
            $reason = __('People tagged this quote for moments when you feel :mood, and as a quote that gives :goal.', ['mood' => $moodName, 'goal' => $goalName]);
        } elseif ($quote->goal_match) {
            $reason = __('This quote was tagged as one that gives :goal.', ['goal' => $goalName]);
        } elseif ($quote->mood_match) {
            $reason = __('This quote was tagged for moments when you feel :mood.', ['mood' => $moodName]);
        } else {
            return __('It is in the :category category, which often gives :goal.', ['category' => $quote->category?->label(), 'goal' => $goalName]);
        }

        if ($inGoalCategory) {
            $reason .= ' '.__('Its category (:category) also fits this goal.', ['category' => $quote->category->label()]);
        }

        return $reason;
    }

    public static function label(Quote $quote): string
    {
        return match (true) {
            $quote->goal_match && $quote->mood_match => __('Perfect match'),
            (bool) $quote->goal_match => __('Fits your goal'),
            (bool) $quote->mood_match => __('Fits your mood'),
            default => __('From a related category'),
        };
    }
}
