<?php

namespace LittleGreenMan\Earhart\Controllers;

use Illuminate\Http\Request;

class AuthOrgMembersController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $organisationId)
    {
        return redirect(config('services.propelauth.auth_url').'/org/members/'.$organisationId);
    }
}
