<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\Translator;

class TranslationController extends Controller
{
    public function show(Quote $quote, Translator $translator)
    {
        $result = $translator->translate($quote);

        if (! $result) {
            return response()->json(['message' => __('Translation is not available right now. Please try again later.')], 503);
        }

        return response()->json($result + ['note' => __('Machine translation')]);
    }
}
