@extends('Layout.AuthImg')

@section('title', __('Log In'))
@section('form')
    <form method="post" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <h1 class="text-3xl font-bold text-white">{{ __('Welcome back') }}</h1>
            <p class="mt-1 text-tuwaiq-lilac">{{ __('Log in to like, save and share quotes.') }}</p>
        </div>

        @include('partials.errors')

        <div>
            <label for="email" class="label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" dir="ltr" value="{{ old('email') }}" autocomplete="email" class="input peer user-invalid:border-red-500" required
                pattern="[^@\s]+@[^@\s]+\.[A-Za-z]{2,}">
            <p class="hint peer-user-invalid:text-red-300">{{ __('A valid email, e.g. name@example.com') }}</p>
        </div>

        <div>
            <label for="password" class="label">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" class="input peer user-invalid:border-red-500" required minlength="8">
            <p class="hint peer-user-invalid:text-red-300">{{ __('At least 8 characters.') }}</p>
        </div>

        <button type="submit" class="btn-primary w-full">{{ __('Log In') }}</button>

        <p class="text-center text-sm text-tuwaiq-lilac">
            {{ __('New to QuoteIt?') }} <a href="{{ route('signup') }}" class="font-semibold text-tuwaiq-teal hover:underline">{{ __('Create an account') }}</a>
        </p>
    </form>
@endsection
