# Signing in

Framework Cloud supports four sign-in methods. All four methods access the same account.

## Email links

Enter your email address on the sign-in page to receive a one-time link. Each link expires after 15 minutes.

Email security scanners such as Outlook Safe Links may prefetch URLs before delivery. Opening the link alone does not consume it. Opening it in the same browser signs you in directly, while opening it in another browser displays a confirmation **Sign in** button.

The email also contains a six-digit code. Select **Have the code? Enter it instead** on the **Check your email** page and enter it there. The code works only in the browser that requested the email, expires with the link, and stops working after five wrong attempts.

## Session length

Sign-ins last one year. Select **This is a shared computer** on the sign-in page to end the sign-in after two hours without use instead.

To end sign-ins on other browsers, such as after losing a device, open **Account > Sign-in & security** (`/account/security`) and select **Sign out other devices** under **Other devices**. This browser stays signed in.

## Passkeys

A passkey signs you in with Touch ID, Face ID, Windows Hello, or a hardware security key. Add a passkey in **Account > Sign-in & security** (`/account/security`) after your first sign-in. You can register multiple passkeys across your devices.

## GitHub

When GitHub sign-in is configured on the server, **Sign in with GitHub** appears on the sign-in page. First-time sign-in creates an account from your GitHub profile or links to an existing account with a matching verified email address.

## Passwords

Self-hosted servers enable password authentication by default. Framework Public Cloud disables passwords.

New accounts have no password set. Open **Account > Sign-in & security** (`/account/security`) and use the **Password** panel to set one. Passwords require at least 10 characters. Once set, you can sign in with your email address and password and optionally disable automatic sign-in emails. Select the password reset link on the sign-in page to request a reset email.

Server administrators can also set an account password from the command line before configuring email:

```sh
php artisan fw:password you@example.edu
```

## Account handle and public profile

Set a handle in **Account > Account details**. Handles use 2 to 30 lowercase letters, numbers, and hyphens, and start with a letter or number. Route and brand words such as `admin` and `docs` are reserved. Readable artifact URLs use the handle, such as `/@ada/nhanes-study/main-results`.

**Public profile** publishes a page at `/@handle` with your name, affiliation, join date, and up to 50 public artifacts without passwords. The profile requires a handle and stays hidden until you turn it on.

## Sign-in and public email

Your account email address is private and used only for authentication and account notices. Update it in **Account > Account details**.

Your public author email address is separate and appears in generated projects, notebooks, and reports. Set or clear it in **Settings > Personal data**.

## Account deletion

Open **Account > Account details**, go to **Account deletion**, and select **Delete account**. Deletion permanently removes your settings, projects, tokens, published artifacts, collections, and reports. Enter your account email address to confirm.
