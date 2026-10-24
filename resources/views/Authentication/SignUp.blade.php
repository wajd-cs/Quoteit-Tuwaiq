@extends('Layout.AuthImg')

@section('title', __('Sign Up'))
@section('form')
    @php $chosen = collect(old('interests', []))->map(fn ($id) => (int) $id); @endphp

    <form method="post" action="{{ route('signup') }}" class="space-y-5">
        @csrf
        <div>
            <h1 class="text-3xl font-bold text-white">{{ __('Join QuoteIt') }}</h1>
            <p class="mt-1 text-tuwaiq-lilac">{{ __('Share the words that move you.') }}</p>
        </div>

        @include('partials.errors')

        <div>
            <label for="name" class="label">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" class="input peer user-invalid:border-red-500"
                required minlength="2" maxlength="50" pattern="[A-Za-z؀-ۿ ]+">
            <p class="hint peer-user-invalid:text-red-300">{{ __('2–50 characters, letters only.') }}</p>
        </div>

        <div>
            <label for="email" class="label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" dir="ltr" value="{{ old('email') }}" autocomplete="email" class="input peer user-invalid:border-red-500" required
                pattern="[^@\s]+@[^@\s]+\.[A-Za-z]{2,}">
            <p class="hint peer-user-invalid:text-red-300">{{ __('A valid email, e.g. name@example.com') }}</p>
        </div>

        <div>
            <label for="password" class="label">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="input peer user-invalid:border-red-500" required minlength="8">
            <p class="hint peer-user-invalid:text-red-300">{{ __('At least 8 characters.') }}</p>
        </div>

        <fieldset>
            <legend class="label">{{ __('What do you like to read?') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></legend>
            <p class="hint mt-0 mb-3">{{ __('We\'ll build your “For you” feed from these.') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <label class="chip cursor-pointer has-checked:border-tuwaiq-teal has-checked:bg-tuwaiq-teal/15 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-tuwaiq-teal">
                        <input type="checkbox" name="interests[]" value="{{ $category->id }}" class="sr-only" @checked($chosen->contains($category->id))>
                        {{ $category->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <button type="submit" class="btn-primary w-full">{{ __('Sign Up') }}</button>

        <p class="text-center text-sm text-tuwaiq-lilac">
            {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold text-tuwaiq-teal hover:underline">{{ __('Log In') }}</a>
        </p>
    </form>
@endsection
