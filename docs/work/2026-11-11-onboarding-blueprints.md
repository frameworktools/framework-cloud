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

## Onboarding refinements

The setup steps now have slash separators. Website and ORCID are visible fields with optional labels. The affiliation help text reads, “We can use this in notebooks/reports you publish.”

Starting blueprint uses the requested description and a wider layout. The selected starter's resolved notebook format, packages, AI skills, Git, sensitivity, and folder tree appear beside the choices on desktop and below them on mobile. Switching starters refreshes the preview. The existing preview component supplies a compact mode; its other displays keep their existing layout.

Validation: 18 focused tests passed with 468 assertions. The production frontend build passed. Browser checks verified slash count, visible optional fields, preview updates for Presentation and Shiny, desktop column positions, mobile overflow, and no page errors or Vue warnings. Light, dark, and mobile screenshots were inspected.


## Intermediate saves

Completed setup steps now save to `users.onboarding_progress`. Author details and default language also save to their normal account settings when their steps are completed. Blueprint edits and the destination section save before Continue, Back, or section navigation. Returning to setup resumes the saved section. Session drafts from the prior implementation are imported on first use.

The final action publishes the private default blueprint and clears the progress record. Skipping keeps author details and language already saved and discards the unfinished blueprint draft. The progress field is hidden from user serialization. Local browser storage still retains edits within a section until its next save.

Regression checks cover refresh, session replacement, intermediate validation failure, account isolation, final cleanup, and exact blueprint field restoration. A browser test resumed the profile and language steps after refresh and restored the blueprint name, notebook choice, and current section in a new browser context. No page errors occurred.

The author form now shows one empty affiliation row automatically. Entirely empty rows are ignored when saving; partially completed affiliations still require an institution name. Validation and normalization apply in both onboarding and Personal data. The focused onboarding, blueprint builder, and account settings run passed 36 tests with 831 assertions. The browser pass verified the empty row, continued without filling it, and resumed progress in fresh browser contexts.
