<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request, Quote $quote)
    {
        // a scheduled quote opens for likes, saves and comments once it is published
        abort_if($quote->isScheduled(), 403);

        $result = $request->user()->likedQuotes()->toggle($quote->id);
        $liked = count($result['attached']) > 0;

        $badges = $this->rewardBadges($request);

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
