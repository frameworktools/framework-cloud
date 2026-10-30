@php
    $heading = $isNewAccount ? 'Confirm your account' : 'Sign in to Framework Cloud';
    $action = $isNewAccount ? 'Confirm and sign in' : 'Sign in';
    $minutes = \App\Models\LoginToken::LIFETIME_MINUTES;
    $font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
    <!--[if mso]>
    <style>* { font-family: Arial, sans-serif !important; }</style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden;">
        {{ $isNewAccount ? 'Confirm your email to finish creating your Framework Cloud account.' : "Your sign-in link expires in {$minutes} minutes." }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f5f5" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 480px;">
                    <tr>
                        <td align="center" style="padding: 0 0 24px; font-family: {!! $font !!}; font-size: 18px; font-weight: 600; color: #171717;">
                            Framework <span style="color: #0284c7;">Cloud</span>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" style="background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 36px 32px; font-family: {!! $font !!}; color: #262626;">
                            <h1 style="margin: 0 0 12px; font-size: 20px; line-height: 28px; font-weight: 600; color: #171717;">{{ $heading }}</h1>

                            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #404040;">
                                @if ($isNewAccount)
                                    Confirm your email to finish creating your account. After that, you can add a passkey in Settings to sign in with one touch.
                                @else
                                    Use the button below to sign in. The link works once and expires in {{ $minutes }} minutes.
                                @endif
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td bgcolor="#0284c7" style="background-color: #0284c7; border-radius: 6px;">
                                        <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 12px 24px; border: 1px solid #0284c7; border-radius: 6px; font-family: {!! $font !!}; font-size: 15px; font-weight: 600; line-height: 20px; color: #ffffff; text-decoration: none;">{{ $action }}</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 28px 0 0; font-size: 13px; line-height: 20px; color: #737373;">
                                If the button doesn't work, paste this link into your browser:
                            </p>
                            <p style="margin: 4px 0 0; font-size: 13px; line-height: 20px; word-break: break-all;">
                                <a href="{{ $url }}" target="_blank" style="color: #0369a1; text-decoration: underline;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 24px 16px 0; font-family: {!! $font !!}; font-size: 12px; line-height: 18px; color: #a3a3a3;">
                            If you didn't request this email, you can ignore it.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
