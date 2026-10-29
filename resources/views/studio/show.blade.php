@extends('Layout.app')

@section('title', __('Quote design studio'))
@section('content')
    @php
        $templates = [
            'night' => [__('Tuwaiq night'), ['#2b1659', '#10081f'], '#ffffff', '#f2a54a'],
            'sunset' => [__('Sunset'), ['#f2a54a', '#c2417a'], '#ffffff', '#2b1659'],
            'sea' => [__('Sea'), ['#4fc9b0', '#1d4e89'], '#ffffff', '#f7e9c5'],
            'paper' => [__('Paper'), ['#f7f1e3', '#efe4cc'], '#2b1659', '#c2417a'],
            'midnight' => [__('Midnight'), ['#0b0b0f', '#0b0b0f'], '#f5f5f5', '#4fc9b0'],
            'lilac' => [__('Lilac'), ['#e9e3ff', '#c9bdf5'], '#2b1659', '#53398f'],
        ];
        $fonts = [
            'Almarai' => 'Almarai',
            'Alexandria' => 'Alexandria',
            'Cairo' => 'Cairo',
            'Rubik' => 'Rubik',
            'Readex Pro' => 'Readex Pro',
        ];
        $isArabic = (bool) preg_match('/\p{Arabic}/u', $quote->content);
        $studioData = [
            'text' => $quote->content,
            'author' => $quote->author ?: $quote->user->name,
            'rtl' => $isArabic,
            'quoteId' => $quote->id,
            'designedUrl' => auth()->check() ? route('studio.designed', $quote) : null,
            'shareUrl' => route('quotes.show', $quote),
            'sharedUrl' => route('quotes.shared', $quote),
            'fileName' => 'quoteit-' . $quote->id . '.png',
            'fonts' => [
                'Alexandria' => asset('fonts/Alexandria.ttf'),
                'Cairo' => asset('fonts/Cairo.ttf'),
                'Rubik' => asset('fonts/Rubik.ttf'),
            ],
            'templates' => collect($templates)->map(fn ($t) => ['bg' => $t[1], 'text' => $t[2], 'accent' => $t[3]]),
        ];
    @endphp

    <div class="mx-auto max-w-6xl px-4 pt-8">
        <a href="{{ route('quotes.show', $quote) }}" class="mb-3 inline-flex items-center gap-2 text-sm text-tuwaiq-lilac hover:text-white">
            <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" /> {{ __('Back to the quote') }}
        </a>
        <p class="text-sm font-semibold text-tuwaiq-teal">{{ __('Make it yours') }}</p>
        <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('Quote design studio') }}</h1>
        <p class="mt-1 max-w-2xl text-tuwaiq-lilac">{{ __('Change the background, colours and font, then save the quote as an image or share it.') }}</p>

        @if (in_array(request('for'), ['instagram', 'snapchat'], true))
            {{-- came from the share window: story size is picked for them --}}
            <p class="mt-4 flex items-center gap-3 rounded-2xl border border-tuwaiq-teal/40 bg-tuwaiq-teal/10 p-3 text-sm" role="note">
                <x-brand-tile :name="request('for')" />
                <span>{{ __('Pick a look, then press “Share image” and choose :app. On a computer the image is saved so you can post it from the app.', ['app' => request('for') === 'instagram' ? __('Instagram') : __('Snapchat')]) }}</span>
            </p>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5" data-studio data-config="{{ json_encode($studioData) }}">
            {{-- preview --}}
            <div class="lg:col-span-3">
                <div class="panel sticky top-20 flex items-center justify-center p-4">
                    <canvas width="1080" height="1080" class="h-auto max-h-[70vh] w-auto max-w-full rounded-2xl shadow-2xl" data-studio-canvas
                        role="img" aria-label="{{ __('Preview of the quote image') }}: {{ $quote->content }}"></canvas>
                </div>
            </div>

            {{-- controls --}}
            <form class="panel space-y-5 p-5 lg:col-span-2" data-studio-form onsubmit="return false">
                <fieldset>
                    <legend class="label">{{ __('Template') }}</legend>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($templates as $key => [$label, $bg, $text, $accent])
                            <label class="option-card flex-col items-center gap-1.5 p-2 text-center text-xs">
                                <input type="radio" name="template" value="{{ $key }}" class="sr-only" @checked($loop->first)>
                                <span class="block h-10 w-full rounded-lg ring-1 ring-white/20" style="background: linear-gradient(135deg, {{ $bg[0] }}, {{ $bg[1] }})" aria-hidden="true">
                                    <span class="flex h-full items-center justify-center text-base font-bold" style="color: {{ $text }}">Aa</span>
                                </span>
                                <span class="text-white">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label">{{ __('Size') }}</legend>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        @foreach (['square' => [__('Square'), '1080×1080'], 'story' => [__('Story'), '1080×1920'], 'wide' => [__('Wide'), '1200×630']] as $value => [$label, $dims])
                            <label class="option-card flex-col items-center gap-0 p-2 text-center">
                                <input type="radio" name="size" value="{{ $value }}" class="sr-only" @checked($value === (in_array(request('for'), ['instagram', 'snapchat'], true) ? 'story' : 'square'))>
                                <span class="font-semibold text-white">{{ $label }}</span>
                                <span class="text-[11px] text-tuwaiq-lilac tabular-nums">{{ $dims }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="grid grid-cols-2 gap-3">
                    <legend class="label col-span-2">{{ __('Colours') }}</legend>
                    @foreach (['bg1' => __('Background 1'), 'bg2' => __('Background 2'), 'text' => __('Text'), 'accent' => __('Accent')] as $name => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-white/15 p-2 text-sm">
                            <input type="color" name="{{ $name }}" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="studio-font" class="label text-sm">{{ __('Font') }}</label>
                        <select id="studio-font" name="font" class="input">
                            @foreach ($fonts as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="studio-align" class="label text-sm">{{ __('Alignment') }}</label>
                        <select id="studio-align" name="align" class="input">
                            <option value="center">{{ __('Centre') }}</option>
                            <option value="start">{{ __('Start of line') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="studio-size" class="label text-sm">{{ __('Text size') }} <span class="font-normal text-tuwaiq-lilac tabular-nums" data-studio-size-out></span></label>
                    <input id="studio-size" type="range" name="fontSize" min="60" max="140" value="100" class="w-full accent-tuwaiq-orange">
                </div>

                <div class="space-y-2">
                    <label class="toggle"><input type="checkbox" name="showAuthor" checked><span class="text-sm text-white">{{ __('Show who said it') }}</span></label>
                    <label class="toggle"><input type="checkbox" name="showMarks" checked><span class="text-sm text-white">{{ __('Big quote marks') }}</span></label>
                    <label class="toggle"><input type="checkbox" name="showBrand" checked><span class="text-sm text-white">{{ __('Add “QuoteIt” at the bottom') }}</span></label>
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" class="btn-secondary" data-studio-download><x-icon name="download" /> {{ __('Save image') }}</button>
                    <button type="button" class="btn-primary" data-studio-share><x-icon name="share" /> {{ __('Share image') }}</button>
                </div>
                <p class="hint mt-0" data-studio-share-hint>{{ __('On phones, “Share image” opens WhatsApp, X, Instagram and more. On computers the image is saved so you can attach it anywhere.') }}</p>
            </form>
        </div>
    </div>
@endsection
