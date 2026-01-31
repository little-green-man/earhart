<?php

namespace LittleGreenMan\Earhart\Controllers;

use Illuminate\Http\Request;

class AuthAccountController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return redirect(config('services.propelauth.auth_url').'/account');
    }
}
