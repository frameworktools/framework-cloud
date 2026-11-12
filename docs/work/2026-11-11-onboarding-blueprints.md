# Blueprint onboarding

Onboarding now begins with an introduction to blueprints, projects, and published results. It collects author details, an optional public email, website, ORCID, and up to five affiliations. The sign-in email stays separate.

The person chooses a default language and a compatible starting blueprint. The existing blueprint editor then provides sequential navigation through notebook format, project structure, environment reproducibility, packages, scaffold settings, editor files, AI agents, AI skills, Git, publishing, and review. Stata omits the notebook, managed environment, scaffold, and editor sections.

Finish setup saves a private, single-language blueprint, makes it the default for the chosen language, saves the author settings, and completes onboarding in one database transaction. The other languages' defaults stay intact. The completion page links to project creation, computer connection instructions, and blueprint editing.

Introductory answers remain in a session draft until completion. Skipping discards them. The builder keeps its draft in browser session storage while navigating and refreshing. Completion clears that browser draft. Validation errors preserve the draft. The draft belongs to the signed-in account. A second submit after completion cannot create another blueprint from that draft.

Author validation and normalization are shared with Personal data through `AuthorProfile` and `AuthorFields`. The old guided setup path that wrote legacy defaults and converted them into a blueprint has been removed from the Welcome controller.

## Validation

The full Cloud suite passed 712 tests with 6,980 assertions before the additional starter matrix test was added. The focused onboarding and Personal data checks passed. The additional starter matrix passed with the onboarding tests (six tests, 319 assertions). Browser flows completed for R on desktop and Stata at 390px. Light, dark, and mobile screenshots were inspected. Both flows produced no page errors; the desktop flow also checked Vue warnings and verified that blueprint edits survive a refresh. The frontend build and structure editor JavaScript checks passed.

The starter matrix checks each supported language for every built-in starting blueprint. It submits the editor's populated choices through the final onboarding endpoint and checks the saved language and structure.

## Production reset

Erik's production account was reset again before implementation. It was already empty. Login, admin privileges, GitHub sign-in, other accounts, and built-in content were preserved. A private server backup was saved at `/home/forge/backups/account-reset-erik-20261112-012438`. Onboarding remains incomplete so the new flow is shown next time.

## Preview

The checkout preview is https://framework-cloud.pequod.dev/welcome, discovered with `tunnels`.
