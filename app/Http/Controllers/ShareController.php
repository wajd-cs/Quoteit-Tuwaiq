<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteShare;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShareController extends Controller
{
    /**
     * Counts a copy / share / image download, used for "most shared".
     * Called in the background by the page, so it answers with "204 No Content".
     */
    public function store(Request $request, Quote $quote)
    {
        $validated = $request->validate(['channel' => ['required', Rule::in(QuoteShare::CHANNELS)]]);

        $quote->shares()->create([
            'user_id' => $request->user()?->id,
            'channel' => $validated['channel'],
        ]);

        return response()->noContent();
    }
}
