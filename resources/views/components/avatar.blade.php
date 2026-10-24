@props(['user', 'size' => 'md'])

@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-20 w-20 text-2xl', 'xl' => 'h-24 w-24 text-3xl'];
    $palette = ['from-tuwaiq-purple to-tuwaiq-teal', 'from-tuwaiq-purple to-tuwaiq-orange', 'from-tuwaiq-teal to-tuwaiq-purple-light', 'from-tuwaiq-orange to-tuwaiq-purple-light'];
    $gradient = $palette[$user->id % count($palette)];
    $avatarUrl = $user->avatarUrl();
@endphp

@if ($avatarUrl)
    <img src="{{ $avatarUrl }}" alt="" {{ $attributes->merge(['class' => "inline-block shrink-0 rounded-full object-cover ring-2 ring-white/20 {$sizes[$size]}"]) }}>
@else
    <span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br {$gradient} font-bold text-white ring-2 ring-white/20 {$sizes[$size]}"]) }}
        aria-hidden="true">{{ $user->initials() }}</span>
@endif
