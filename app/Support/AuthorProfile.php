<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/** Author metadata shared by onboarding and Personal data. */
class AuthorProfile
{
    public const MAX_AFFILIATIONS = 5;

    public static function for(User $user): array
    {
        $author = Arr::get($user->globalSettings()->document, 'author', []) ?? [];
        $affiliations = collect($author['affiliations'] ?? [])->map(fn ($a) => Arr::only((array) $a, ['name', 'department', 'city', 'country']) + ['name' => '', 'department' => '', 'city' => '', 'country' => ''])->values()->all();
        if (! $affiliations && filled($author['affiliation'] ?? null)) {
            $affiliations = [['name' => $author['affiliation'], 'department' => '', 'city' => '', 'country' => '']];
        }

        return ['name' => ($author['name'] ?? '') ?: $user->name, 'email' => $author['email'] ?? '', 'orcid' => $author['orcid'] ?? '', 'url' => $author['url'] ?? '', 'affiliations' => $affiliations];
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'orcid' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'affiliations' => ['array', 'max:'.self::MAX_AFFILIATIONS],
            'affiliations.*.name' => ['required', 'string', 'max:190'],
            'affiliations.*.department' => ['nullable', 'string', 'max:190'],
            'affiliations.*.city' => ['nullable', 'string', 'max:120'],
            'affiliations.*.country' => ['nullable', 'string', 'max:120'],
        ];
    }

    public static function messages(): array
    {
        return ['orcid.regex' => 'Write the ORCID iD as 0000-0002-1825-0097.', 'affiliations.*.name.required' => 'Each affiliation needs a name.'];
    }

    public static function save(User $user, array $data): void
    {
        $affiliations = collect($data['affiliations'] ?? [])->map(fn ($a) => array_filter(Arr::only($a, ['name', 'department', 'city', 'country']), 'filled'))->values()->all();
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['author'] = ['name' => $data['name'], 'email' => $data['email'] ?? '', 'affiliation' => $affiliations[0]['name'] ?? '', 'orcid' => $data['orcid'] ?? '', 'url' => $data['url'] ?? '', 'affiliations' => $affiliations];
        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);
    }
}
