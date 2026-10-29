<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Arabic <-> English for quotes, using the free MyMemory service.
 * Each translation is saved, so the service is asked only once per quote.
 */
class Translator
{
    /** @return array{text: string, lang: string}|null */
    public function translate(Quote $quote): ?array
    {
        $fromArabic = (bool) preg_match('/\p{Arabic}/u', $quote->content);
        [$from, $to] = $fromArabic ? ['ar', 'en'] : ['en', 'ar'];

        $saved = $quote->translations()->where('lang', $to)->value('text');
        if ($saved) {
            return ['text' => $saved, 'lang' => $to];
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get('https://api.mymemory.translated.net/get', array_filter([
                'q' => $quote->content,
                'langpair' => "{$from}|{$to}",
                // an email gives a bigger free daily limit
                'de' => config('services.mymemory.email'),
            ]));
        } catch (Throwable) {
            return null;
        }

        $text = html_entity_decode((string) $response->json('responseData.translatedText'), ENT_QUOTES | ENT_HTML5);
        if (! $response->ok() || (int) $response->json('responseStatus') !== 200 || trim($text) === '' || $text === $quote->content) {
            return null;
        }

        $quote->translations()->create(['lang' => $to, 'text' => $text]);

        return ['text' => $text, 'lang' => $to];
    }
}
