@props(['quote', 'url', 'text'])

@php
    $message = $text . "\n\n" . __('Shared from QuoteIt');
    $enc = rawurlencode($message);
    $encUrl = rawurlencode($url);
    $links = [
        ['X (Twitter)', 'x', "https://twitter.com/intent/tweet?text={$enc}&url={$encUrl}"],
        ['WhatsApp', 'whatsapp', 'https://wa.me/?text=' . rawurlencode($message . "\n" . $url)],
        ['Telegram', 'telegram', "https://t.me/share/url?url={$encUrl}&text={$enc}"],
        ['LinkedIn', 'linkedin', "https://www.linkedin.com/sharing/share-offsite/?url={$encUrl}"],
        ['Facebook', 'facebook', "https://www.facebook.com/sharer/sharer.php?u={$encUrl}"],
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

        <div class="grid grid-cols-2 gap-2">
            @foreach ($links as [$label, $channel, $href])
                <a href="{{ $href }}" target="_blank" rel="noopener" class="btn-ghost justify-start px-4 py-2 text-sm" data-share-channel="{{ $channel }}" data-share-url="{{ $sharedUrl }}">{{ $label }}</a>
            @endforeach
            <button type="button" class="btn-ghost justify-start px-4 py-2 text-sm" data-copy="{{ $url }}" data-share-channel="link" data-share-url="{{ $sharedUrl }}">
                <x-icon name="link" class="h-4 w-4" /> {{ __('Copy link') }}
            </button>
        </div>

        <a href="{{ route('studio', $quote) }}" class="btn-secondary mt-3 w-full">{{ __('Design it as an image') }}</a>

        <button type="button" class="btn-primary mt-3 hidden w-full" data-native-share data-title="QuoteIt" data-text="{{ $message }}" data-url="{{ $url }}" data-share-channel="native" data-share-url="{{ $sharedUrl }}">
            {{ __('More options…') }}
        </button>
    </div>
</div>
