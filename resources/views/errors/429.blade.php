@extends('Layout.app')

@section('title', __('Too many tries'))
@section('content')
    <div class="mx-auto max-w-lg px-4 py-20 text-center">
        <h1 class="text-3xl font-bold text-white">{{ __('Too many tries') }}</h1>
        <p class="mt-3 text-tuwaiq-lilac">{{ __('For your safety we paused this for a minute. Please wait a little and try again.') }}</p>
        <a href="{{ url()->previous() }}" class="btn-primary mt-6">{{ __('Go back') }}</a>
    </div>
@endsection
