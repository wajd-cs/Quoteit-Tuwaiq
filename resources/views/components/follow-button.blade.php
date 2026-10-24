@props(['user'])

@php $following = auth()->user()->isFollowing($user); @endphp

<form method="POST" action="{{ route('users.follow', $user) }}" data-toggle="follow">
    @csrf
    <button type="submit" aria-pressed="{{ $following ? 'true' : 'false' }}"
        class="btn-secondary px-4 py-2 text-sm aria-pressed:bg-white/10 aria-pressed:text-white"
        data-on="{{ __('Followed') }}" data-off="{{ __('Follow') }}">
        <span data-label>{{ $following ? __('Followed') : __('Follow') }}</span>
    </button>
</form>
