<?php

namespace App\Http\Controllers;

use App\Services\NotificationFeed;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** The bell was opened: quotes seen so far are no longer "new". */
    public function seen(Request $request)
    {
        $request->user()->update(['notifications_seen_at' => now()]);

        return response()->noContent();
    }

    /** Small check the page makes every few seconds to update the numbers on the bell and Messages. */
    public function live(Request $request, NotificationFeed $feed)
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $feed->for($user)['count'],
            'messages' => $feed->unreadMessages($user),
        ]);
    }
}
