# Dashboard and research navigation

The sidebar now separates Dashboard, Projects, and Default settings from the Your research group. That group contains Artifacts, Collections, and Reports. Every left-menu item uses an outline Heroicon. The Projects page lists projects. Collections and Reports have account-wide indexes, while their project-specific indexes remain available.

Dashboard is the default destination from the authenticated landing-page header and hero, the app logo, the breadcrumb home icon, onboarding, and sign-in. Explicit intended destinations remain intact. The dashboard combines recent artifacts, collections, and reports in one activity table. It also lists recent projects and account-scoped totals. New accounts still receive guided setup.

Artifact rows show the project beneath the title. The column label is Type. View and Edit are text-only ghost actions. Collections, reports, and dashboard activity use the same action labels. Narrow tables retain those actions and truncate long titles without horizontal scrolling. The primary button's inset highlight was removed.

## Validation

93 focused dashboard, workspace, onboarding, and authentication tests passed with 802 assertions. Earlier collection, report, artifact workflow, and published-page checks passed. Account-wide indexes exclude other accounts and deleted projects. The frontend build and diff whitespace checks passed.

The shared browser was used to inspect the layouts in light mode, dark mode, and at 390px. Dashboard and Artifacts had no document or table overflow and no nested buttons. The authenticated landing header and hero both pointed to `/dashboard`. Public routing reached Cloudflare Access; authenticated public rendering was not checked.

## Local preview

`tunnels` maps this checkout to https://framework-cloud.pequod.dev. Dashboard is https://framework-cloud.pequod.dev/dashboard and Artifacts is https://framework-cloud.pequod.dev/published. Built assets are active. The local-only Vite hot marker was removed.

Changes remain local and uncommitted. Existing work from the prior skills, layout, and prose changes was preserved. No other project was edited for this task.
