# Sign-in link plan, from Academic.page (2026-11-09)

Erik asked for this plan to reach Framework Cloud too. Academic.page is
building it now; Framework Cloud has the same parts (`MagicLinkController`,
`MagicLinkMail`), so the same plan may fit here.

## The problem

Sending a sign-in link only to accounts without a password, or showing a
different screen to accounts that prefer a password, tells anyone who types
an address whether it has an account and how its owner signs in.

## The plan

1. After someone enters an email address, the next screen is always the
   same: "Check your email. If this address has an account, we sent a
   sign-in link." Below it, "Use your password instead".
2. A sign-in link is emailed automatically when the account exists, unless
   its owner turned off a new setting, "Email me a sign-in link when I sign
   in" (on by default). Someone who prefers passwords turns it off and gets
   no email; the screen does not change.
3. A browser that signed in with a password last time opens straight to the
   password field next time. This is a cookie in that browser only, so it
   reveals nothing about any account.
4. An account with no password signs in with the link only. "Use your
   password instead" still shows, and no password works.
5. Sending stays rate-limited, and a second link is not sent within about a
   minute of the first.

Nothing on screen differs between an address with an account and one
without. Only the account's own inbox shows the difference.
