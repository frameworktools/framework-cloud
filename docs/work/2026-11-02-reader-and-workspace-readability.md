# Workspace and model reader readability

Replaced the signed-in workspace cards with compact semantic lists for outputs, reports, collections, and projects. Removed introductory panels, repeated card footers, and the project tile/list switch. Creation actions remain in section headers, with project selectors only when multiple projects exist. Existing owner scoping, scaffold status polling, and management links remain.

Narrowed typed artifact readers to 896 px. Model readers now lead with the formula and coefficient table, followed by a smaller 672 px coefficient plot and fit summary. Human-readable coefficient labels replace raw field names. Source class details are collapsed. Sort indicators use SVG chevrons rather than emoji-rendered Unicode arrows.

Numeric display defaults to three significant digits. A per-table full-precision selector restores the original values, which are also available in cell tooltips. Sorting, CSV export, and stored JSON preserve original precision. Exact identifiers stored as strings remain unchanged. Confidence levels display as percentages, and interval methods have readable labels.

Verified the actual R model reader in the browser at desktop and phone sizes, including precision switching and numeric sorting. Verified the workspace at both sizes. No page-level horizontal overflow; wide coefficient tables scroll within their panel on phones. Seven JavaScript tests and ten focused PHP tests (161 assertions) pass. Production build passes with the existing main-chunk size warning. Removed the ignored Vite hot file after building so previews use built assets, without requiring remote access to a local development asset server.

`tunnels` identifies this checkout as https://framework-cloud.pequod.dev. The public browser request reaches Cloudflare Access; this browser lacks an authenticated Access session, so authenticated public rendering was not verified. Local origin rendering and built asset loading were verified. Shared tunnel and Access configuration remain unchanged.

## Rich-text reports and tabular model summaries

Replaced report Markdown textareas with Tiptap visual HTML editors. Formatting controls support headings, emphasis, underline, lists, quotes, links, undo, and redo. Markdown input and paste rules are disabled. New blocks carry `format: html`. Symfony HTML Sanitizer applies an explicit element/attribute allowlist both on save and render. Existing legacy text is converted to sanitized HTML when opened for editing; published database snapshots are unchanged.

The report builder now uses one sidebar and a narrow white writing canvas. Title, introduction, and report sections come first. Settings, sharing, publication history, and deletion are collapsed. Artifact selectors and version controls share a compact row, with optional inline previews. Removed uppercase eyebrow headings from report editing, listing, and reading. Artifact readers also use a white background in light mode. The editor bundle loads only when the editor is needed.

Replaced model-summary metric tiles with a semantic Statistic/Value table. Row groups are labeled Sample and degrees of freedom, Model fit, Inference, and Additional statistics when applicable. Heading colors support both themes. Display rounding and exact source values remain as described above.

Live browser verification created report 2 with heading, bold, link, and bulleted list through real editor controls. Saved, reopened, and published it successfully; verified the reader's white background and formatted HTML. Published example: https://framework-cloud.pequod.dev/r/laosfy9iydzmyqbz58nxtxyh/versions/1. Verified rich editor phone layout has no horizontal page overflow. Verified real model-summary table labels and rounded values through the browser. The public Access-session limitation described above still applies.

Validation: 22 focused PHP tests (206 assertions), seven reader JavaScript tests, the updated report-builder browser fixture (formatting, reorder/remove, version pinning, sharing consent, password-preserving saves), Pint, and production build pass. Tests cover HTML sanitization, legacy editing compatibility, and immutable published HTML. Tiptap integration follows https://tiptap.dev/docs/editor/getting-started/install/vue3 and sanitization follows https://symfony.com/doc/current/html_sanitizer.html.

## Final display corrections

Erik clarified that only the model metadata table should size to its contents. Restored the wider coefficient table and reader (896 px); metadata uses `w-fit`/`w-auto`. Numeric values and plot ticks use monospace fonts. Coefficient tables preserve model term order and have no sorting buttons; ordinary data tables remain sortable.

Precision means decimal places, not significant digits. Default floating-point display is three decimal places (37.227, 1.600, -0.032). The table control is a numeric stepper from 0 to 15 decimal places. For models it also updates numeric summary values. Integer counts and exact string identifiers retain their values. Original full values are available in hover titles, including the server-rendered fallback; exports remain exact. Verified numeric stepper editing and ArrowUp/ArrowDown behavior in the actual Blade/JS browser fixture, along with hover values and existing table behavior. Reader unit tests, focused display tests, and production build pass.

## Navigation, controls, and preview depth (2026-11-03)

Artifact breadcrumbs now sit directly above the title, use an outline home and compact chevrons, and end in a chevron so the title completes the trail. Collection memberships are linked in the trail; multiple memberships are peer links. Navigation and the published-artifact index use Artifacts instead of Published outputs. Copy link and other secondary actions in artifact, report, and collection management use ghost buttons; this preference is recorded in cloud-design.

The decimal input has explicit, always-visible increase/decrease chevrons, with disabled states at its bounds. Numeric keyboard controls still work. The preview shadow is broader and lighter (shadow-lg with 6% slate in light mode, 20% black in dark mode).

Verified the artifact detail page in Chrome at desktop light/dark and 390px, including screenshots and no document overflow. Browser table tests verify both explicit arrow clicks and keyboard precision changes. Published-page tests (5 tests, 53 assertions), focused artifact display tests, and frontend build pass. Existing unrelated trailing whitespace in Projects/Show.vue was left alone.
