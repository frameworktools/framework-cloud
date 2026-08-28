<x-mail::message>
@if ($isNewAccount)
# Welcome to Framework

Click below to confirm your email and sign in. After that, add a passkey in
Settings for one-touch sign-in.
@else
# Sign in to Framework

Click below to sign in. The link works once and expires in
{{ \App\Models\LoginToken::LIFETIME_MINUTES }} minutes.
@endif

<x-mail::button :url="$url">
Sign in
</x-mail::button>

If you didn't request this, you can ignore this email.

{{ config('app.name') }}
</x-mail::message>
