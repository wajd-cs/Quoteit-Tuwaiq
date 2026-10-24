<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Quote design studio: the image is drawn in the browser (canvas),
 * the server only shows the page and counts finished designs for achievements.
 */
class StudioController extends Controller
{
    public function show(Quote $quote)
    {
        return view('studio.show', [
            'page' => 'Studio',
            'quote' => $quote->load(['user', 'category']),
        ]);
    }

    public function designed(Request $request, Quote $quote)
    {
        $request->validate(['template' => ['nullable', 'string', 'max:40']]);

        DB::table('quote_designs')->insert([
            'user_id' => $request->user()->id,
            'quote_id' => $quote->id,
            'template' => $request->input('template'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['saved' => true, 'badges' => $this->rewardBadges($request)]);
    }
}
