@extends('Layout.app')


@section('content')
    <div class="mx-auto grid grid-cols-1 max-w-5xl items-center gap-10 px-4 py-10 md:grid-cols-2">
        <div class="panel p-6 sm:p-9">
            @yield('form')
        </div>
        <div class="hidden md:block">
            <img src="{{ asset('images/signup.jpeg') }}" alt="{{ __('Tuwaiq Academy building') }}"
                class="mx-auto max-h-[560px] w-full max-w-sm rounded-3xl object-cover shadow-2xl shadow-black/50 ring-1 ring-white/10">
        </div>
    </div>
@endsection
