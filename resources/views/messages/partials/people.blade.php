{{-- list of people to start a chat with (used by the "New message" box) --}}
@forelse ($people as $person)
    <form method="POST" action="{{ route('messages.start', $person) }}">
        @csrf
        <button type="submit" class="flex w-full items-center gap-3 rounded-2xl p-2.5 text-start transition hover:bg-white/5">
            <x-avatar :user="$person" />
            <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold text-white">{{ $person->name }}</span>
                @if ($person->bio)
                    <span class="block truncate text-xs text-tuwaiq-lilac" dir="auto">{{ $person->bio }}</span>
                @endif
            </span>
            <x-icon name="chat" class="h-5 w-5 shrink-0 text-tuwaiq-teal" />
        </button>
    </form>
@empty
    <p class="px-3 py-8 text-center text-sm text-tuwaiq-lilac">
        {{ $term === '' ? __('No friends to message yet. Follow people, or search for a name above.') : __('No one found with this name.') }}
    </p>
@endforelse
