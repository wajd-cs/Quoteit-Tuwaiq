<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function toggle(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, __('You cannot follow yourself.'));

        $result = $request->user()->following()->toggle($user->id);
        $following = count($result['attached']) > 0;

        $badges = $this->rewardBadges($request);

        if ($request->expectsJson()) {
            return response()->json([
                'badges' => $badges,
                'active' => $following,
                'count' => $user->followers()->count(),
            ]);
        }

        return redirect()->back();
    }
}
