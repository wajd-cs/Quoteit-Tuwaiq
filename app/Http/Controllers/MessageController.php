<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        return view('messages.index', [
            'page' => 'Messages',
            'following' => $request->user()->following()->orderBy('name')->get(),
            'conversations' => $this->conversationsFor($request->user()),
            'active' => null,
            'messages' => collect(),
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->includes($user), 403);

        $this->markRead($conversation, $user);

        return view('messages.index', [
            'page' => 'Messages',
            'conversations' => $this->conversationsFor($user),
            'following' => $user->following()->orderBy('name')->get(),
            'active' => $conversation->load('userOne', 'userTwo'),
            'other' => $conversation->otherThan($user),
            'messages' => $conversation->messages()->latest('id')->limit(100)->get()->reverse()->values(),
        ]);
    }

    /**
     * People to start a chat with: the ones you follow, or anyone whose name matches the search.
     */
    public function people(Request $request)
    {
        $me = $request->user();
        $term = trim((string) $request->query('q', ''));

        $people = $term === ''
            ? $me->following()->orderBy('name')->get()
            : User::query()->whereKeyNot($me->id)->whereLike('name', "%{$term}%")->orderBy('name')->limit(15)->get();

        return view('messages.partials.people', ['people' => $people, 'term' => $term]);
    }

    /** "Message" button on a profile: open the chat with that person. */
    public function start(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422);

        return redirect()->route('messages.show', Conversation::between($request->user(), $user));
    }

    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->includes($user), 403);

        $data = $request->validate(
            ['body' => ['required', 'string', 'max:'.Message::MAX_LENGTH]],
            ['body.required' => __('Write a message first.')],
        );

        $message = $conversation->messages()->create(['sender_id' => $user->id, 'body' => trim($data['body'])]);
        $conversation->update(['last_message_at' => $message->created_at]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->present($message, $user)], 201);
        }

        return redirect()->route('messages.show', $conversation);
    }

    /** New messages after the last one on screen (the chat checks every few seconds). */
    public function poll(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->includes($user), 403);

        $messages = $conversation->messages()->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->get();
        $this->markRead($conversation, $user);

        return response()->json(['messages' => $messages->map(fn ($m) => $this->present($m, $user))->values()]);
    }

    private function conversationsFor(User $user)
    {
        return Conversation::query()
            ->for($user)
            ->whereNotNull('last_message_at')
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->orderByDesc('last_message_at')
            ->get();
    }

    private function markRead(Conversation $conversation, User $user): void
    {
        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $user->id)->update(['read_at' => now()]);
    }

    /** @return array{id: int, mine: bool, body: string, time: string} */
    private function present(Message $message, User $user): array
    {
        return [
            'id' => $message->id,
            'mine' => $message->sender_id === $user->id,
            'body' => $message->body,
            'time' => $message->created_at->translatedFormat('g:i A'),
        ];
    }
}
