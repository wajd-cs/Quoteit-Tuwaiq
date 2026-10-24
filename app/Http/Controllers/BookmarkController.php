<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('users.show', [$request->user(), 'tab' => 'saved']);
    }

    public function toggle(Request $request, Quote $quote)
    {
        // a scheduled quote opens for likes, saves and comments once it is published
        abort_if($quote->isScheduled(), 403);

        $result = $request->user()->bookmarkedQuotes()->toggle($quote->id);
        $saved = count($result['attached']) > 0;

        $badges = $this->rewardBadges($request);

        if ($request->expectsJson()) {
            return response()->json(['active' => $saved, 'badges' => $badges]);
        }

        return redirect()->to(url()->previous().'#quote-'.$quote->id);
    }
}
