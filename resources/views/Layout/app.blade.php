@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $prefs = \App\Support\Preferences::for(auth()->user());
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
    data-contrast="{{ $prefs['contrast'] }}" data-colors="{{ $prefs['colors'] }}"
    data-motion="{{ $prefs['reduce_motion'] ? 'reduce' : 'full' }}" data-links="{{ $prefs['underline_links'] ? 'underline' : 'plain' }}"
    data-spacing="{{ $prefs['readable_spacing'] ? 'wide' : 'normal' }}" data-speech-rate="{{ $prefs['speech_rate'] }}"
    data-signed-in="{{ auth()->check() ? '1' : '0' }}" data-theme="{{ $prefs['theme'] }}"
    style="--qscale: {{ $prefs['text_scale'] }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'QuoteIt') · QuoteIt</title>
    @stack('meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- guests keep their display settings in the browser --}}
    @guest
        <script>
            try {
                const p = JSON.parse(localStorage.getItem('qi-prefs') || '{}');
                const s = localStorage.getItem('qi-scale');
                const d = document.documentElement;
                if (p.contrast) d.dataset.contrast = p.contrast;
                if (p.colors) d.dataset.colors = p.colors;
                if ('reduce_motion' in p) d.dataset.motion = p.reduce_motion ? 'reduce' : 'full';
                if ('underline_links' in p) d.dataset.links = p.underline_links ? 'underline' : 'plain';
                if ('readable_spacing' in p) d.dataset.spacing = p.readable_spacing ? 'wide' : 'normal';
                if (p.speech_rate) d.dataset.speechRate = p.speech_rate;
                if (p.theme) d.dataset.theme = p.theme;
                if (p.text_scale || s) d.style.setProperty('--qscale', p.text_scale || s);
            } catch (e) {}
        </script>
    @endguest

    @php
        $qiConfig = [
            'locale' => $locale,
            'savePrefsUrl' => route('accessibility.update'),
            't' => [
                'saved_fav' => __('Saved to favourites'),
                'removed_fav' => __('Removed from favourites'),
                'copied' => __('Copied'),
                'copy_failed' => __('Could not copy'),
                'no_speech' => __('Your browser cannot read text aloud.'),
                'text_size' => __('Text size'),
                'choose_mood' => __('Choose how you feel first.'),
                'choose_goal' => __('Choose what you need right now.'),
                'check_choices' => __('Please check your choices.'),
                'load_failed' => __('Something went wrong while loading quotes. Check your connection and try again.'),
                'added_history' => __('Added to your history'),
                'save_failed' => __('Could not save. Please try again.'),
                'settings_saved' => __('Settings saved'),
                'new_badge' => __('New badge'),
                'image_saved' => __('Image saved'),
                'stop' => __('Stop'),
                'to_light' => __('Switch to light mode'),
                'to_dark' => __('Switch to dark mode'),
            ],
        ];
    @endphp
    <script>
        window.QI = {{ Js::from($qiConfig) }};
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-tuwaiq-night bg-fixed bg-bottom bg-no-repeat font-sans text-tuwaiq-text antialiased"
    style="background-image: linear-gradient(to bottom, rgba(16,8,31,.55), rgba(16,8,31,.25)), url('{{ asset('images/background.png') }}'); background-size: cover;">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:text-tuwaiq-night">{{ __('Skip to content') }}</a>

    <x-navbar :page="$page ?? ''" />

    <main id="main" class="pb-28" tabindex="-1">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-center text-xs text-tuwaiq-lilac/80">
        {{ __('QuoteIt · Built in the Tuwaiq Club bootcamp · Umm Al-Qura University') }}
        · <a href="{{ route('accessibility') }}" class="underline-offset-2 hover:underline">{{ __('Accessibility') }}</a>
        <p class="mt-2" dir="ltr">&copy; {{ now()->year }} Wajd Alluhaibi. All rights reserved.</p>
    </footer>

    {{-- shows while a quote is being read aloud: change the speed or stop right here --}}
    <div id="speech-bar" hidden role="region" aria-label="{{ __('Reading aloud') }}"
        class="fixed inset-x-3 bottom-3 z-[60] mx-auto max-w-xl rounded-2xl border border-white/15 bg-tuwaiq-ink/95 p-3 shadow-2xl shadow-black/50 backdrop-blur-md">
        <div class="flex items-center gap-2">
            <x-icon name="speaker" class="h-5 w-5 shrink-0 text-tuwaiq-teal" />
            <p class="flex min-w-0 flex-1 gap-1 text-sm text-white"><span class="shrink-0 text-tuwaiq-lilac">{{ __('Reading now:') }}</span> <span class="min-w-0 truncate" dir="auto" data-speech-text></span></p>
            <button type="button" class="btn-secondary shrink-0 px-3 py-1.5 text-sm" data-speech-stop>{{ __('Stop') }}</button>
        </div>
        <div class="mt-2 flex items-center gap-1.5" role="group" aria-label="{{ __('Reading speed') }}">
            <span class="me-1 hidden text-xs text-tuwaiq-lilac sm:inline">{{ __('Speed') }}</span>
            @foreach (\App\Support\Preferences::OPTIONS['speech_rate'] as $rate)
                <button type="button" class="speed-chip" data-rate="{{ $rate }}" aria-pressed="false" dir="ltr">{{ $rate }}×</button>
            @endforeach
        </div>
    </div>

    <div id="toast" data-show="false" role="status" aria-live="polite"
        class="fixed bottom-28 left-1/2 z-[70] -translate-x-1/2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-tuwaiq-night shadow-2xl"></div>

    @if (session('badges'))
        <span hidden data-new-badges='@json(session('badges'))'></span>
    @endif
</body>

</html>
