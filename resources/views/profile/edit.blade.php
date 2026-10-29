@extends('Layout.app')

@section('title', __('Edit profile'))
@section('content')
    @php $chosen = collect(old('interests', $user->interests->pluck('id')->all()))->map(fn ($id) => (int) $id); @endphp

    <div class="mx-auto max-w-2xl px-4 pt-8">
        <form method="POST" action="{{ route('profile.update') }}" class="panel space-y-6 p-6 sm:p-8" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="lg" />
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ __('Edit profile') }}</h1>
                    <p class="text-sm text-tuwaiq-lilac">{{ __('This is what people see when they open your profile.') }}</p>
                </div>
            </div>

            <div>
                <label for="avatar" class="label">{{ __('Profile picture') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></label>
                <div class="flex flex-wrap items-center gap-4">
                    <img data-avatar-preview src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover ring-2 ring-white/20 {{ $user->avatarUrl() ? '' : 'hidden' }}">
                    <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp" class="input file:me-3 file:rounded-lg file:border-0 file:bg-tuwaiq-purple file:px-3 file:py-1.5 file:text-white" data-avatar-input>
                </div>
                <p class="hint">{{ __('JPG, PNG or WebP, up to 2 MB.') }}</p>
                @if ($user->avatar_path)
                    <label class="mt-2 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remove_avatar" value="1" class="h-4 w-4 accent-tuwaiq-orange"> {{ __('Remove my picture') }}
                    </label>
                @endif
            </div>

            @include('partials.errors')

            <div>
                <label for="name" class="label">{{ __('Name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required minlength="2" maxlength="50"
                    pattern="[A-Za-z؀-ۿ ]+" class="input peer user-invalid:border-red-500">
                <p class="hint peer-user-invalid:text-red-300">{{ __('2–50 characters, letters only.') }}</p>
            </div>

            <div>
                <label for="bio" class="label">{{ __('Bio') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></label>
                <textarea id="bio" name="bio" rows="2" maxlength="160" dir="auto" data-counter class="input resize-none" placeholder="{{ __('A line about you') }}">{{ old('bio', $user->bio) }}</textarea>
                <p class="hint flex justify-between"><span>{{ __('Up to 160 characters.') }}</span><span data-counter-output></span></p>
            </div>

            <div>
                <label for="contact_link" class="label">{{ __('Contact link') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></label>
                <input id="contact_link" name="contact_link" type="text" value="{{ old('contact_link', $user->contact_link) }}" maxlength="255"
                    class="input" placeholder="{{ __('https://x.com/yourname or you@example.com') }}" dir="ltr">
                <p class="hint">{{ __('A link (X, LinkedIn, website) or an email. It shows as a “Contact” button on your profile. Your login email stays private.') }}</p>
            </div>

            <fieldset>
                <legend class="label">{{ __('Your interests') }}</legend>
                <p class="hint mb-3 mt-0">{{ __('We use these for your “For you” feed and recommendations.') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <label class="chip cursor-pointer has-checked:border-tuwaiq-teal has-checked:bg-tuwaiq-teal/15 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-tuwaiq-teal">
                            <input type="checkbox" name="interests[]" value="{{ $category->id }}" class="sr-only" @checked($chosen->contains($category->id))>
                            {{ $category->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset id="notifications" class="scroll-mt-24">
                <legend class="label">{{ __('Notifications') }}</legend>
                <input type="hidden" name="notify_new_quotes" value="0">
                <label class="toggle">
                    <input type="checkbox" name="notify_new_quotes" value="1" @checked(old('notify_new_quotes', $user->notify_new_quotes))>
                    <span class="text-sm text-white">{{ __('Tell me when someone I follow posts a new quote') }}</span>
                </label>
                <p class="hint">{{ __('Off by default. New messages always show in the bell.') }}</p>
            </fieldset>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">{{ __('Save profile') }}</button>
                <a href="{{ route('users.show', $user) }}" class="btn-ghost">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
