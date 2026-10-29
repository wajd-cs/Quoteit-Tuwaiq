<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /** Wrong passwords allowed per minute for the same email and device. */
    private const MAX_ATTEMPTS = 5;

    public function login(LoginRequest $request)
    {
        $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Too many tries. Please wait :seconds seconds and try again.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (Auth::attempt($request->validated())) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

        RateLimiter::hit($key, 60);

        return redirect()->back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __('The entered information is not correct')]);
    }
}
