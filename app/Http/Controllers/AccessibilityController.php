<?php

namespace App\Http\Controllers;

use App\Support\Preferences;
use Illuminate\Http\Request;

class AccessibilityController extends Controller
{
    public function index(Request $request)
    {
        return view('accessibility.index', [
            'page' => 'Accessibility',
            'prefs' => Preferences::for($request->user()),
            'options' => Preferences::OPTIONS,
        ]);
    }

    /**
     * Saves settings automatically (called by the page on every change).
     * Guests keep settings in their browser, so nothing is stored for them here.
     */
    public function update(Request $request)
    {
        $clean = Preferences::clean($request->all());

        if ($request->user()) {
            $request->user()->update([
                'preferences' => array_merge(Preferences::for($request->user()), $clean),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'stored' => $request->user() ? 'account' : 'browser', 'preferences' => $clean]);
        }

        return redirect()->route('accessibility')->with('status', __('Settings saved'));
    }
}
