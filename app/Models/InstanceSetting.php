<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of this server's own credentials, saved from the admin setup page.
 * Read through App\Support\Credentials, never directly.
 */
class InstanceSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
