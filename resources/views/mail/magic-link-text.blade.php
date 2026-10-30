{{ $isNewAccount ? 'Confirm your Framework Cloud account' : 'Sign in to Framework Cloud' }}

@if ($isNewAccount)
Open this link to confirm your email and finish creating your account:
@else
Open this link to sign in. It works once and expires in {{ \App\Models\LoginToken::LIFETIME_MINUTES }} minutes.
@endif

{{ $url }}

If you didn't request this email, you can ignore it.
