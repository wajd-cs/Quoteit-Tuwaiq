<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What goes in the bell: new quotes from people I follow (only if I turned it on)
 * and messages I have not read yet. Worked out when asked, so scheduled quotes
 * only show up once they are published.
 */
class NotificationFeed
{
    /**
     * @return array{items: Collection<int, array{type: string, user: User, at: CarbonInterface, new: bool, quote?: Quote, message?: Message}>, count: int}
     */
    public function for(User $user, int $limit = 12): array
    {
        $items = collect();
        $seen = $user->notifications_seen_at;

        if ($user->notify_new_quotes) {
            Quote::query()
                ->with('user')
                ->whereIn('user_id', $user->following()->select('users.id'))
                ->where('quotes.published_at', '>=', now()->subDays(14))
                ->latest('quotes.published_at')
                ->limit($limit)
                ->get()
                ->each(fn (Quote $quote) => $items->push([
                    'type' => 'quote',
                    'user' => $quote->user,
                    'quote' => $quote,
                    'at' => $quote->published_at,
                    'new' => $seen === null || $quote->published_at->gt($seen),
                ]));
        }

        $this->unreadQuery($user)
            ->with('sender')
            ->latest('id')
            ->get()
            ->unique('conversation_id')
            ->each(fn (Message $message) => $items->push([
                'type' => 'message',
                'user' => $message->sender,
                'message' => $message,
                'at' => $message->created_at,
                'new' => true,
            ]));

        $items = $items->sortByDesc(fn ($item) => $item['at']->getTimestamp())->take($limit)->values();

        return ['items' => $items, 'count' => $items->where('new', true)->count()];
    }

    /** Messages sent to me that I have not opened yet. */
    public function unreadMessages(User $user): int
    {
        return $this->unreadQuery($user)->count();
    }

    private function unreadQuery(User $user)
    {
        return Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->whereIn('conversation_id', Conversation::query()->for($user)->select('id'));
    }
}
