<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Support\QuoteStyle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:155'],
            'author' => ['nullable', 'string', 'max:80'],
            'hours' => ['required', 'integer', Rule::in(Story::LIFETIMES)],
            'style' => ['nullable', 'array'],
            'style.*' => ['nullable', 'string', 'max:20'],
        ]);

        $request->user()->stories()->create([
            'content' => $data['content'],
            'author' => $data['author'] ?? null,
            'style' => QuoteStyle::clean(($data['style'] ?? []) + ['align' => 'center']),
            'expires_at' => now()->addHours((int) $data['hours']),
        ]);

        return redirect()->route('home')->with('status', __('Your story is up for :hours hours.', ['hours' => $data['hours']]));
    }

    public function like(Request $request, Story $story)
    {
        abort_unless($story->expires_at->isFuture(), 404);

        $result = $story->likers()->toggle($request->user()->id);

        return response()->json([
            'active' => count($result['attached']) > 0,
            'count' => $story->likers()->count(),
        ]);
    }

    public function destroy(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $story->delete();

        return redirect()->route('home')->with('status', __('Story deleted.'));
    }
}
