<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SignupController extends Controller
{
    public function signup(SignupRequest $request)
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));
        $user->interests()->sync($request->validated('interests', []));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home', $user->interests()->exists() ? ['tab' => 'for-you'] : [])
            ->with('status', __('Welcome to QuoteIt, :name!', ['name' => $user->name]));
    }
}
