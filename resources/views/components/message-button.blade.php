@props(['user'])

{{-- opens (or starts) the private chat with this person --}}
<form method="POST" action="{{ route('messages.start', $user) }}">
    @csrf
    <button type="submit" class="btn-ghost px-4 py-2 text-sm"><x-icon name="chat" class="h-4 w-4" /> {{ __('Message') }}</button>
</form>
