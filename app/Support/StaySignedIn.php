<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Every sign-in keeps the person signed in (auth.guards.web.remember, a
 * year) unless they say it's a shared computer. As on academic.page.
 */
class StaySignedIn
{
    public const FIELD = 'shared_device';

    public static function wanted(Request $request): bool
    {
        return ! $request->boolean(self::FIELD);
    }
}
