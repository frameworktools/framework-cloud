# Getting started

This page uses Framework Public Cloud at framework.pub. On your own server,
replace framework.pub with your server's address.

## 1. Create an account

Open [framework.pub/register](https://framework.pub/register) and sign up with
GitHub or your email address. There is no password to set. If you sign up by
email, you get a link that confirms the address and signs you in.

You can sign up with a personal email address. The address you sign in with
stays private. The email shown on your work comes from your profile, which
you set in the next step. See [Signing in](signing-in.md).

## 2. Fill in your profile

Open **Settings > Profile** and enter your name, the email you want shown on
your work, your ORCID iD, your website, and up to five affiliations. Framework
writes these into the author block of every new notebook, report, and slide
deck.

## 3. Connect R

Framework Cloud talks to R with a token.

1. Open **API tokens** and create a token. Copy it. It is shown only once.
2. Install Framework in R, then connect:

   ```r
   framework::cloud_login("<your token>")
   ```

`cloud_login()` shows what connecting shares and asks you to confirm. The
token is stored in your system's credential store, such as the macOS
Keychain. Run it once on each computer you use.

On your own server, add the server's address. The **API tokens** page shows
the full command:

```r
framework::cloud_login("<your token>", url = "https://framework.example.edu")
```

R remembers the server, so later commands such as `framework::new()` use it.
Run `framework::cloud_status()` to see which server R is using.

## 4. Start a project

Open **Projects > New project**.

1. Choose a blueprint. Open one to see the folders, packages, and templates it
   sets up.
2. Name the project.
3. Choose where to set it up:
   - **On your computer.** You get a project token. In R, run
     `framework::setup("<project token>")` and Framework builds the project.
   - **On GitHub.** Framework Cloud creates the repository and builds the
     project there. Clone it and start working. See
     [GitHub integration](github.md).

## 5. Publish a notebook

From inside the project in R:

```r
framework::publish("notebooks/analysis.qmd")
```

Framework renders the notebook and uploads it. The result is a link you can
send to collaborators. See [Publishing notebooks](publishing.md).
