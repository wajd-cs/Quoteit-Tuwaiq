<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Models\Quote;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\WeeklySummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function me()
    {
        return redirect()->route('users.show', Auth::user());
    }

    public function show(Request $request, User $user)
    {
        $isMe = Auth::check() && Auth::id() === $user->id;
        $tab = $request->query('tab', 'quotes');

        // saved tab is private
        if (in_array($tab, ['saved', 'scheduled', 'drafts'], true) && ! $isMe) {
            $tab = 'quotes';
        }

        $achievements = app(AchievementService::class);
        $achievements->sync($user);

        $user->loadCount(['quotes', 'followers', 'following', 'likedQuotes', 'bookmarkedQuotes', 'comments']);

        $stats = [
            'quotes' => $user->quotes_count,
            'likes_received' => DB::table('likes')
                ->join('quotes', 'quotes.id', '=', 'likes.quote_id')
                ->where('quotes.user_id', $user->id)
                ->count(),
            'likes_given' => $user->liked_quotes_count,
            'saved' => $user->bookmarked_quotes_count,
            'comments' => $user->comments_count,
            'followers' => $user->followers_count,
            'following' => $user->following_count,
        ];

        // top categories from the quotes this user liked
        $taste = Quote::query()
            ->whereIn('id', $user->likedQuotes()->select('quotes.id'))
            ->whereNotNull('category_id')
            ->with('category')
            ->get()
            ->countBy(fn ($q) => $q->category->label())
            ->sortDesc()
            ->take(5);

        $quotes = match ($tab) {
            'liked' => Quote::withCardData()
                ->whereIn('id', $user->likedQuotes()->select('quotes.id'))
                ->sortBy('latest'),
            'saved' => Quote::withCardData()
                ->whereIn('id', $user->bookmarkedQuotes()->select('quotes.id'))
                ->sortBy('latest'),
            'scheduled' => Quote::withCardData()->scheduledBy($user)->orderBy('quotes.published_at'),
            'drafts' => Quote::withCardData()->draftsBy($user)->orderByDesc('quotes.updated_at'),
            default => Quote::withCardData()->where('user_id', $user->id)->sortBy('latest'),
        };

        return view('profile.show', [
            'page' => $isMe ? 'Profile' : '',
            'user' => $user->load('interests'),
            'isMe' => $isMe,
            'isFollowing' => Auth::check() && ! $isMe && Auth::user()->isFollowing($user),
            'stats' => $stats,
            'taste' => $taste,
            'badges' => $achievements->earnedBadges($user),
            'points' => $achievements->points($user),
            'tab' => in_array($tab, ['quotes', 'liked', 'saved', 'scheduled', 'drafts']) ? $tab : 'quotes',
            'scheduledCount' => $isMe ? Quote::scheduledBy($user)->count() : 0,
            'draftsCount' => $isMe ? Quote::draftsBy($user)->count() : 0,
            'weekly' => $isMe ? app(WeeklySummary::class)->for($user) : null,
            'quotes' => $quotes->paginate(12)->withQueryString(),
        ]);
    }

    public function edit()
    {
        return view('profile.edit', [
            'page' => 'Edit profile',
            'user' => Auth::user()->load('interests'),
        ]);
    }

    public function update(ProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->safe()->only(['name', 'bio', 'contact_link']) + ['notify_new_quotes' => $request->boolean('notify_new_quotes')]);
        $user->interests()->sync($request->validated('interests', []));

        // profile picture
        if ($request->hasFile('avatar') || $request->boolean('remove_avatar')) {
            if ($user->avatar_path) {
                Storage::disk('local')->delete($user->avatar_path);
            }
            $user->update([
                'avatar_path' => $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'local') : null,
            ]);
        }

        return redirect()->route('users.show', $user)->with('status', __('Profile saved.'));
    }
}
