{{ $isNewAccount ? 'Confirm your Framework account' : 'Sign in to Framework' }}

@if ($isNewAccount)
Open this link to confirm your email and finish creating your account:
@else
Open this link to sign in. It works once and expires in {{ \App\Models\LoginToken::LIFETIME_MINUTES }} minutes.
@endif

{{ $url }}

If you didn't request this email, you can ignore it.
