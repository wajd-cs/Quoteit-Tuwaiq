@extends('Layout.app')

@section('title', __('Accessibility center'))
@section('content')
    @php
        $colorModes = [
            'default' => [__('Default colours'), __('The normal QuoteIt colours'), ['#432a80', '#f2a54a', '#4fc9b0', '#e5484d']],
            'protanopia' => [__('Red-blind friendly (protanopia)'), __('Avoids red; uses blue and orange'), ['#0072b2', '#e69f00', '#56b4e9', '#d55e00']],
            'deuteranopia' => [__('Green-blind friendly (deuteranopia)'), __('Avoids red/green pairs; uses blue and yellow'), ['#0072b2', '#f0e442', '#56b4e9', '#cc79a7']],
            'tritanopia' => [__('Blue-blind friendly (tritanopia)'), __('Avoids blue/yellow pairs; uses red and teal'), ['#d55e00', '#009e73', '#cc79a7', '#000000']],
            'mono' => [__('Greyscale'), __('No colours, only light and dark'), ['#555555', '#999999', '#cccccc', '#ffffff']],
        ];
        $speechLabels = ['0.75' => __('Slow'), '1' => __('Normal'), '1.25' => __('A bit faster'), '1.5' => __('Fast'), '2' => __('Very fast')];
    @endphp

    <div class="mx-auto max-w-4xl px-4 pt-8">
        <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('Make QuoteIt work for you') }}</p>
        <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Accessibility center') }}</h1>
        <p class="mt-1 max-w-2xl text-tuwaiq-lilac">
            {{ __('Changes apply right away and are saved automatically') }}
            @auth {{ __('to your account, so they follow you on any device.') }} @else {{ __('in this browser. Log in to keep them on every device.') }} @endauth
        </p>

        <form method="POST" action="{{ route('accessibility.update') }}" class="mt-6 space-y-6" data-prefs-form>
            @csrf
            @method('PUT')

            <p class="sr-only" role="status" aria-live="polite" data-prefs-status></p>

            {{-- contrast --}}
            <fieldset class="panel p-5 sm:p-6">
                <legend class="sr-only">{{ __('Contrast') }}</legend>
                <h2 class="text-lg font-bold text-white">{{ __('Contrast') }}</h2>
                <p class="hint mt-0">{{ __('High contrast makes text and borders stronger for people with low vision.') }}</p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach (['normal' => __('Normal contrast'), 'high' => __('High contrast')] as $value => $label)
                        <label class="option-card">
                            <input type="radio" name="contrast" value="{{ $value }}" class="sr-only" @checked($prefs['contrast'] === $value)>
                            <span class="font-semibold text-white">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- colour modes --}}
            <fieldset class="panel p-5 sm:p-6">
                <legend class="sr-only">{{ __('Colour mode') }}</legend>
                <h2 class="text-lg font-bold text-white">{{ __('Colour mode') }}</h2>
                <p class="hint mt-0">{{ __('Colour palettes that stay easy to tell apart for people with colour blindness.') }}</p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($colorModes as $value => [$label, $description, $swatches])
                        <label class="option-card items-start">
                            <input type="radio" name="colors" value="{{ $value }}" class="sr-only" @checked($prefs['colors'] === $value)>
                            <span class="flex shrink-0 gap-1 pt-1" aria-hidden="true">
                                @foreach ($swatches as $swatch)
                                    <span class="h-4 w-4 rounded-full ring-1 ring-white/30" style="background: {{ $swatch }}"></span>
                                @endforeach
                            </span>
                            <span><span class="block font-semibold text-white">{{ $label }}</span><span class="block text-xs text-tuwaiq-lilac">{{ $description }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- text --}}
            <fieldset class="panel p-5 sm:p-6">
                <legend class="sr-only">{{ __('Text') }}</legend>
                <h2 class="text-lg font-bold text-white">{{ __('Text') }}</h2>
                <label for="text_scale" class="label mt-3">{{ __('Quote text size') }}</label>
                <select id="text_scale" name="text_scale" class="input sm:w-64">
                    @foreach ($options['text_scale'] as $scale)
                        <option value="{{ $scale }}" @selected((float) $prefs['text_scale'] === (float) $scale)>{{ round($scale * 100) }}%</option>
                    @endforeach
                </select>
                <p class="quote-text mt-3 rounded-2xl bg-white/5 p-4 text-white" data-text-preview>{{ __('“Small steps every day add up to big results.”') }}</p>

                <div class="mt-4 space-y-2">
                    <label class="toggle">
                        <input type="hidden" name="readable_spacing" value="0"><input type="checkbox" name="readable_spacing" value="1" @checked($prefs['readable_spacing'])>
                        <span><span class="block font-semibold text-white">{{ __('Easier reading spacing') }}</span><span class="block text-xs text-tuwaiq-lilac">{{ __('More space between lines, words and letters.') }}</span></span>
                    </label>
                    <label class="toggle">
                        <input type="hidden" name="underline_links" value="0"><input type="checkbox" name="underline_links" value="1" @checked($prefs['underline_links'])>
                        <span><span class="block font-semibold text-white">{{ __('Underline all links') }}</span><span class="block text-xs text-tuwaiq-lilac">{{ __('So links do not depend on colour.') }}</span></span>
                    </label>
                    <label class="toggle">
                        <input type="hidden" name="reduce_motion" value="0"><input type="checkbox" name="reduce_motion" value="1" @checked($prefs['reduce_motion'])>
                        <span><span class="block font-semibold text-white">{{ __('Reduce motion') }}</span><span class="block text-xs text-tuwaiq-lilac">{{ __('Turns off animations like the heart pop.') }}</span></span>
                    </label>
                </div>
            </fieldset>

            {{-- speech --}}
            <fieldset class="panel p-5 sm:p-6">
                <legend class="sr-only">{{ __('Reading aloud') }}</legend>
                <h2 class="text-lg font-bold text-white">{{ __('Reading aloud') }}</h2>
                <p class="hint mt-0">{{ __('Speed used by the speaker button on every quote.') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($options['speech_rate'] as $rate)
                        <label class="option-card px-4 py-2.5">
                            <input type="radio" name="speech_rate" value="{{ $rate }}" class="sr-only" @checked((float) $prefs['speech_rate'] === (float) $rate)>
                            <span class="font-bold text-white tabular-nums">{{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}×</span>
                            <span class="text-xs text-tuwaiq-lilac">{{ $speechLabels[(string) $rate] ?? '' }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="button" class="btn-ghost mt-4" data-speak="{{ __('This is how quotes will sound at this speed.') }}" data-lang="{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}">
                    <x-icon name="speaker" /> {{ __('Try it') }}
                </button>
            </fieldset>

            {{-- language --}}
            <section class="panel p-5 sm:p-6">
                <h2 class="text-lg font-bold text-white">{{ __('Language') }}</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('locale', 'ar') }}" lang="ar" class="chip {{ app()->getLocale() === 'ar' ? 'chip-active' : '' }}">العربية</a>
                    <a href="{{ route('locale', 'en') }}" lang="en" class="chip {{ app()->getLocale() === 'en' ? 'chip-active' : '' }}">English</a>
                </div>
            </section>

            {{-- keyboard --}}
            <section class="panel p-5 sm:p-6">
                <h2 class="text-lg font-bold text-white">{{ __('Keyboard and screen readers') }}</h2>
                <p class="hint mt-0">{{ __('Every button and link works with the keyboard, has a visible focus outline and a label for screen readers.') }}</p>
                <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    @foreach ([
                        ['Tab', __('Move to the next button or link')],
                        ['Shift + Tab', __('Move back')],
                        [__('Enter / Space'), __('Press a button, like, save or choose an option')],
                        ['Esc', __('Close a box or menu')],
                        ['/', __('Jump to the search box')],
                        [__('Arrow keys'), __('Move between options in a group')],
                    ] as [$key, $action])
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 px-3 py-2">
                            <dt><kbd class="rounded-md border border-white/25 bg-tuwaiq-ink px-2 py-0.5 font-mono text-xs text-white">{{ $key }}</kbd></dt>
                            <dd class="text-tuwaiq-text">{{ $action }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <noscript>
                <button type="submit" class="btn-primary">{{ __('Save settings') }}</button>
            </noscript>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-ghost" data-prefs-reset>{{ __('Reset to default') }}</button>
                <span class="text-sm text-tuwaiq-teal" data-prefs-saved hidden>{{ __('Saved automatically') }}</span>
            </div>
        </form>
    </div>
@endsection
