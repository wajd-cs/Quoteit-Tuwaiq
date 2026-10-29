@props(['quote', 'url', 'text'])

@php
    $message = $text . "\n\n" . __('Shared from QuoteIt');
    $enc = rawurlencode($message);
    $encUrl = rawurlencode($url);
    $studio = route('studio', $quote);
    // Instagram and Snapchat do not accept text from websites, so the quote goes as an image from the studio
    $apps = [
        [__('WhatsApp'), 'whatsapp', 'https://wa.me/?text=' . rawurlencode($message . "\n" . $url), true],
        ['X', 'x', "https://twitter.com/intent/tweet?text={$enc}&url={$encUrl}", true],
        [__('Instagram'), 'instagram', $studio . '?for=instagram', false],
        [__('Snapchat'), 'snapchat', $studio . '?for=snapchat', false],
        [__('Telegram'), 'telegram', "https://t.me/share/url?url={$encUrl}&text={$enc}", true],
        [__('LinkedIn'), 'linkedin', "https://www.linkedin.com/sharing/share-offsite/?url={$encUrl}", true],
        [__('Facebook'), 'facebook', "https://www.facebook.com/sharer/sharer.php?u={$encUrl}", true],
        [__('Email'), 'email', 'mailto:?subject=' . rawurlencode(__('A quote for you')) . '&body=' . rawurlencode($message . "\n" . $url), false],
    ];
    $sharedUrl = route('quotes.shared', $quote);
    // links made on a local machine only work on that machine
    $isLocalLink = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\]|0\.0\.0\.0)(:\d+)?/#i', $url);
@endphp

<div id="share-{{ $quote->id }}" popover class="modal" aria-labelledby="share-title-{{ $quote->id }}">
    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 id="share-title-{{ $quote->id }}" class="text-xl font-bold text-white">{{ __('Share this quote') }}</h2>
            <button type="button" popovertarget="share-{{ $quote->id }}" popovertargetaction="hide" class="action" aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
        </div>

        <p class="mb-5 rounded-2xl bg-white/5 p-4 text-sm whitespace-pre-line text-tuwaiq-text" dir="auto">{{ $message }}
<span class="text-tuwaiq-teal" dir="ltr">{{ $url }}</span></p>

        @if ($isLocalLink)
            <p class="mb-4 rounded-2xl border border-tuwaiq-orange/40 bg-tuwaiq-orange/10 p-3 text-xs text-tuwaiq-text" role="note">
                {{ __('You are running QuoteIt on your own computer, so this link only opens on this device. After the site is published, links use the public address (APP_URL) and work for everyone.') }}
            </p>
        @endif

        <div class="grid grid-cols-4 gap-x-2 gap-y-4 sm:grid-cols-5">
            @foreach ($apps as [$label, $channel, $href, $newTab])
                <a href="{{ $href }}" @if ($newTab) target="_blank" rel="noopener" @endif class="share-app" data-share-channel="{{ $channel }}" data-share-url="{{ $sharedUrl }}">
                    <x-brand-tile :name="$channel" />
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            <button type="button" class="share-app" data-copy="{{ $url }}" data-share-channel="link" data-share-url="{{ $sharedUrl }}">
                <x-brand-tile name="link" />
                <span>{{ __('Copy link') }}</span>
            </button>
        </div>
        <p class="hint mt-4">{{ __('Instagram and Snapchat: the quote is designed as an image, then shared from your phone.') }}</p>

        <button type="button" class="btn-primary mt-3 hidden w-full" data-native-share data-title="QuoteIt" data-text="{{ $message }}" data-url="{{ $url }}" data-share-channel="native" data-share-url="{{ $sharedUrl }}">
            {{ __('More options…') }}
        </button>
    </div>
</div>
