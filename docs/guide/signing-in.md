# Signing in

Framework Cloud has four ways to sign in. You can use any of them with the
same account.

## Email links

Enter your email address and Framework Cloud sends a sign-in link. The link
works once and expires in 15 minutes.

Some email systems, such as Outlook, open links to scan them before you do.
Opening a link doesn't use it up. When you open it in the browser where you
asked for it, you are signed in directly. Anywhere else, you see a page with a
**Sign in** button.

## Passkeys

A passkey signs you in with your fingerprint, face, or device PIN, and there
is nothing to type. Add one in **Settings > Passkeys** after your first
sign-in. You can add one for each device you use.

## GitHub

When the server has GitHub set up, **Sign in with GitHub** appears on the
sign-in page. The first time, it creates an account from your GitHub profile,
or connects to an existing account with the same verified email address.

## Passwords

Self-hosted servers allow passwords by default. Framework Public Cloud does
not.

Accounts start without a password. To add one, open **Settings > Account**
and use the **Password** section. Passwords must have at least 10 characters.
After that you can sign in with your email address and password. If you
forget it, use **Forgot your password?** on the sign-in page to get a reset
link by email.

An admin can set a password from the server, which helps when email isn't set
up yet:

```sh
php artisan fw:password you@example.edu
```

## Your sign-in email and your public email

The email address on your account is used only to sign in and to send you
sign-in links. You can use a personal address.

The email that appears on your work is set separately in
**Settings > Profile**. For example, you can sign in with a personal address
and show your university address on your notebooks.

## Deleting your account

**Settings > Account > Delete account** deletes your settings, projects,
tokens, and published notebooks. Type your account email to confirm.
