<?php

namespace App\Services\GitHub;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Where a new project's repository goes, checked the same way for the New
 * project form and the API: the server can scaffold, the person connected
 * GitHub, the app is installed on the owner, and sensitive projects stay
 * private.
 */
class RepositoryTarget
{
    /**
     * @param  array{owner: string, name: string, private?: bool}  $input
     * @param  string  $field  the validation key errors are reported under, such as "github"
     * @return array{org: ?string, name: string, private: bool}
     */
    public static function for(User $user, array $input, bool $sensitive, string $field = 'github'): array
    {
        $account = $user->githubAccount;

        if (! Scaffolder::enabled()) {
            throw ValidationException::withMessages(["{$field}.enabled" => 'GitHub scaffolding is not set up on this server.']);
        }
        if (! $account) {
            throw ValidationException::withMessages(["{$field}.enabled" => 'Connect GitHub in settings first.']);
        }

        $owner = collect(Installations::for($account) ?? [])->firstWhere('login', $input['owner']);

        if (! $owner) {
            throw ValidationException::withMessages(["{$field}.owner" => 'Install the Framework app on that account first.']);
        }

        $private = (bool) ($input['private'] ?? true);

        if (! $private && $sensitive) {
            throw ValidationException::withMessages(["{$field}.private" => 'This blueprint handles restricted data, so its repository must be private.']);
        }

        return [
            'org' => $owner['type'] === 'Organization' ? $owner['login'] : null,
            'name' => $input['name'],
            'private' => $private,
        ];
    }
}
