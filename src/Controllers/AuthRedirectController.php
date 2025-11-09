<?php

namespace LittleGreenMan\Earhart\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthRedirectController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return Socialite::driver('propelauth')->redirect();
    }
}
