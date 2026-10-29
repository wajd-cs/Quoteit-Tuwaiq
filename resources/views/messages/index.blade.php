@extends('Layout.app')

@section('title', __('Messages'))
@section('content')
    @php $me = auth()->user(); @endphp
    <div class="mx-auto max-w-6xl px-4 pt-8">
        <div @class(['mb-6 flex flex-wrap items-end justify-between gap-3', 'hidden lg:flex' => $active])>
            <div>
                <h1 class="text-3xl font-bold text-white">{{ __('Messages') }}</h1>
                <p class="mt-1 text-tuwaiq-lilac">{{ __('Private chats inside QuoteIt. Only the two of you can read them.') }}</p>
            </div>
            <button type="button" popovertarget="new-chat" class="btn-primary px-4 py-2 text-sm"><x-icon name="plus" /> {{ __('New message') }}</button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[340px_1fr]">
            {{-- conversations (on phones only when no chat is open) --}}
            <section @class(['panel p-2', 'hidden lg:block' => $active]) aria-label="{{ __('Conversations') }}">
                @forelse ($conversations as $conversation)
                    @php
                        $partner = $conversation->otherThan($me);
                        $last = $conversation->latestMessage;
                    @endphp
                    <a href="{{ route('messages.show', $conversation) }}" @class(['flex items-center gap-3 rounded-2xl p-3 transition hover:bg-white/5', 'bg-white/10' => $active?->is($conversation)])>
                        <x-avatar :user="$partner" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate font-semibold text-white">{{ $partner->name }}</span>
                                <span class="shrink-0 text-xs text-tuwaiq-lilac">{{ $conversation->last_message_at?->diffForHumans() }}</span>
                            </span>
                            <span @class(['block truncate text-sm', 'text-white' => $conversation->unread_count, 'text-tuwaiq-lilac' => ! $conversation->unread_count]) dir="auto">
                                @if ($last?->sender_id === $me->id){{ __('You:') }} @endif{{ $last?->body }}
                            </span>
                        </span>
                        @if ($conversation->unread_count)
                            <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-tuwaiq-teal px-1.5 text-xs font-bold text-tuwaiq-night">{{ $conversation->unread_count }}</span>
                        @endif
                    </a>
                @empty
                    <p class="p-6 text-center text-sm text-tuwaiq-lilac">{{ __('No chats yet. Open someone’s profile and press “Message” to start.') }}</p>
                @endforelse
            </section>

            {{-- the open chat --}}
            @if ($active)
                <section class="panel flex h-[calc(100dvh-13rem)] min-h-[420px] flex-col overflow-hidden lg:h-[600px]" aria-label="{{ __('Chat with :name', ['name' => $other->name]) }}">
                    <header class="flex items-center gap-3 border-b border-white/10 p-3">
                        <a href="{{ route('messages.index') }}" class="action lg:hidden" aria-label="{{ __('Back to messages') }}"><x-icon name="arrow-left" class="rtl:rotate-180" /></a>
                        <a href="{{ route('users.show', $other) }}" class="flex items-center gap-3 hover:opacity-90">
                            <x-avatar :user="$other" size="sm" />
                            <span><span class="block font-semibold text-white">{{ $other->name }}</span><span class="block text-xs text-tuwaiq-lilac">{{ __('View profile') }}</span></span>
                        </a>
                    </header>

                    <div class="flex flex-1 flex-col gap-2 overflow-y-auto p-4" data-chat
                        data-poll-url="{{ route('messages.poll', $active) }}" data-last-id="{{ $messages->last()?->id ?? 0 }}" aria-live="polite">
                        @forelse ($messages as $message)
                            <x-message-bubble :mine="$message->sender_id === $me->id" :time="$message->created_at->translatedFormat('g:i A')">{{ $message->body }}</x-message-bubble>
                        @empty
                            <p class="m-auto text-center text-sm text-tuwaiq-lilac" data-chat-empty>{{ __('Say hello to :name.', ['name' => $other->name]) }}</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('messages.store', $active) }}" class="border-t border-white/10 p-3" data-chat-form>
                        @csrf
                        <div class="flex items-end gap-2">
                            <label for="message-body" class="sr-only">{{ __('Write a message') }}</label>
                            <textarea id="message-body" name="body" rows="1" required maxlength="{{ \App\Models\Message::MAX_LENGTH }}" dir="auto"
                                class="input max-h-32 min-h-11 flex-1 resize-none py-2.5" placeholder="{{ __('Write a message…') }}"></textarea>
                            <button type="submit" class="btn-primary h-11 shrink-0 px-4" aria-label="{{ __('Send') }}"><x-icon name="send" class="rtl:-scale-x-100" /></button>
                        </div>
                        <p class="hint mt-1">{{ __('Up to :max characters. Enter sends, Shift + Enter adds a new line.', ['max' => \App\Models\Message::MAX_LENGTH]) }}</p>
                        @error('body')<p class="mt-1 text-sm text-red-300">{{ $message }}</p>@enderror
                    </form>
                </section>
            @else
                <section class="panel hidden min-h-[420px] items-center justify-center p-8 text-center text-tuwaiq-lilac lg:flex">
                    {{ __('Choose a chat to read it.') }}
                </section>
            @endif
        </div>
    </div>

    {{-- yellow + on laptops (phones use the + in the bottom bar) --}}
    <button type="button" popovertarget="new-chat" aria-label="{{ __('New message') }}" title="{{ __('New message') }}"
        class="fab fixed end-6 bottom-6 z-40 flex h-16 w-16 items-center justify-center rounded-full bg-tuwaiq-orange text-tuwaiq-night shadow-xl shadow-black/40 transition hover:scale-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-tuwaiq-orange/40">
        <x-icon name="plus" class="h-8 w-8" />
    </button>

    {{-- new message: people you follow, or search anyone by name --}}
    <div id="new-chat" popover class="modal">
        <div class="space-y-4 p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white">{{ __('New message') }}</h2>
                <button type="button" popovertarget="new-chat" popovertargetaction="hide" class="action" aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
            </div>
            <div>
                <label for="people-search" class="label">{{ __('Who do you want to message?') }}</label>
                <input id="people-search" type="search" dir="auto" autocomplete="off" class="input" placeholder="{{ __('Type a name…') }}"
                    data-people-search="{{ route('messages.people') }}">
                <p class="hint">{{ __('People you follow show first. Type to find anyone else.') }}</p>
            </div>
            <div class="max-h-[50vh] overflow-y-auto" data-people-results aria-live="polite">
                @include('messages.partials.people', ['people' => $following, 'term' => ''])
            </div>
        </div>
    </div>
@endsection
