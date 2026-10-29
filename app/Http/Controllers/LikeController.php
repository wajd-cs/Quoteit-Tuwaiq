<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request, Quote $quote)
    {
        // a scheduled quote opens for likes, saves and comments once it is published
        abort_if($quote->isHidden(), 403);

        $result = $request->user()->likedQuotes()->toggle($quote->id);
        $liked = count($result['attached']) > 0;

        // in-page clicks skip the badge check to answer fast; badges update on the next page load
        $badges = $request->expectsJson() ? [] : $this->rewardBadges($request);

        if ($request->expectsJson()) {
            return response()->json([
                'active' => $liked,
                'count' => $quote->likers()->count(),
                'badges' => $badges,
            ]);
        }

        return redirect()->to(url()->previous().'#quote-'.$quote->id);
    }
}
