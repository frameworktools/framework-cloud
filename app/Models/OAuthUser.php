<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * The person behind a connected AI app's OAuth token, on the same `users`
 * table as User.
 *
 * Passport's and Sanctum's token traits declare the same methods with
 * different signatures, so one model can't carry both. API tokens stay on
 * User (Sanctum); the `api` guard (Passport) signs in this model, which only
 * reads. Use toUser() for anything about the person.
 */
class OAuthUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = ['*'];

    protected $hidden = ['password', 'remember_token'];

    public function getForeignKey(): string
    {
        return 'user_id';
    }

    public function toUser(): User
    {
        return User::query()->findOrFail($this->getKey());
    }
}
