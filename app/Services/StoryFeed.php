<?php

namespace App\Services;

use App\Models\Story;
use App\Models\User;
use App\Support\QuoteStyle;
use Illuminate\Support\Collection;

/** Stories that are still up, grouped by person (mine first, then the newest). */
class StoryFeed
{
    /** @return Collection<int, array{user: User, stories: array<int, array<string, mixed>>, mine: bool}> */
    public function groups(?User $viewer): Collection
    {
        $stories = Story::active()
            ->with('user')
            ->withCount('likers')
            ->withExists(['likers as liked_by_me' => fn ($q) => $q->where('users.id', $viewer?->id ?? 0)])
            ->orderBy('created_at')
            ->get();

        return $stories->groupBy('user_id')
            ->map(fn (Collection $own) => [
                'user' => $own->first()->user,
                'mine' => $viewer !== null && $own->first()->user_id === $viewer->id,
                'latest' => $own->max('created_at'),
                'stories' => $own->map(fn (Story $story) => [
                    'id' => $story->id,
                    'content' => $story->content,
                    'author' => $story->author,
                    'classes' => QuoteStyle::classes($story->style),
                    'rtl' => (bool) preg_match('/\p{Arabic}/u', $story->content),
                    'seconds' => $story->seconds(),
                    'ago' => $story->created_at->diffForHumans(),
                    'left' => trans_choice(':count hour left|:count hours left', max(1, (int) ceil(now()->diffInMinutes($story->expires_at) / 60)), ['count' => max(1, (int) ceil(now()->diffInMinutes($story->expires_at) / 60))]),
                    'likes' => $story->likers_count,
                    'liked' => (bool) $story->liked_by_me,
                    'likeUrl' => route('stories.like', $story),
                    'deleteUrl' => route('stories.destroy', $story),
                ])->values()->all(),
            ])
            ->sortByDesc(fn ($group) => [$group['mine'] ? 1 : 0, $group['latest']->getTimestamp()])
            ->values();
    }
}
