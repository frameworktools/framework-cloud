---
name: cloud-design
description: Design language for Framework Cloud's signed-in app and reader pages. Load before building or restyling any Vue page, Blade reader view, or UI component here, and when a page "feels under-designed".
---

# Framework Cloud design

The signed-in app borrows academic.page's design language (the Pequod project at
`~/Projects/pequod`, which uses Flux UI) while staying in Vue and Tailwind v4.
When in doubt, open an academic.page admin page and match it; the Flux sources
in `~/Projects/pequod/vendor/livewire/flux{,-pro}/stubs/resources/views/flux`
are the reference recipes.

Load the sibling `cloud-prose/SKILL.md` for copy. Use descriptive noun phrases
for headings and labels. Keep explanations short and direct. No jargon, emoji,
or "library" wording.

## Surfaces and structure

- Pages sit on white (dark: slate-950). **No grey panels.** Group content with
  space and hairline rules, not backgrounds.
- Rules are translucent: `border-slate-800/10` (dark `border-white/15`).
  Use `UiSeparator` between page sections and `divide-y` inside lists.
- A bordered white card (`UiCard`, `UiPanel`) is for a self-contained box such
  as a status banner, a filter bar with its results, or a summary. Don't nest
  cards, and don't wrap a whole form in one.
- Forms run in one column, max about `max-w-2xl`, label above control, 24px
  (`gap-6`) between fields, a section heading and description above each group,
  and a rule between groups. The save button sits under the last field.
- Wide pages: main column plus an 18rem sidebar for settings that apply to the
  whole object (sharing, links, delete). The sidebar is plain, sections split
  by rules, not cards.

## Type

- Page title: `PageHeader` (text-2xl semibold). Description under it in
  slate-500 text-sm. Primary action top right.
- Section heading: text-base font-medium slate-900, description text-sm
  slate-500 (`UiSection`).
- Labels text-sm font-medium slate-800; help text text-sm slate-500; errors
  text-sm red-600 (`UiField`).
- Small uppercase eyebrows only for the sidebar's group names.

## Color

- One accent: sky-700 (`--color-accent`), text shade sky-700 in light and
  sky-400 in dark (`--color-accent-content`). It fills the one primary button
  per view and marks the current tab and nav item. Nothing else is blue.
- **Links are not blue by default.** Inline links use `UiLink`: slate-800 with a
  quiet underline. Actions are buttons, never bare blue text.
- Greys are slate. Status colours: emerald for done, amber for warnings, red
  for errors and destructive actions only.

## Components (resources/js/Components)

| Need | Use |
|---|---|
| Actions | `UiButton` — `primary` (one per view), `ghost` (secondary by default, with a gray tint so it reads as a button), `subtle` (quiet), `outline` (only when a border is specifically needed), `danger` (inside a confirm only). Sizes `md` 40px, `sm` 32px, `xs` 24px. `href` renders a link styled as a button; `square` for icon-only. Disabled is 50% opacity, never a pale tint. |
| Text fields | `UiInput`, `UiSelect`, `UiTextarea` (all wrap `UiField`). Bare controls use the recipes in `resources/js/ui.js`. |
| Sections of a page | `UiTabs` (underline tabs, links or v-model), `UiSection`, `UiSeparator` |
| Lists of records | `UiTable` — plain `<thead>/<tbody>`, first and last columns flush, class `strong` on the name cell, `actions` / `numeric` for right-aligned cells. Prefer a table over cards for anything with columns. |
| Where you are | `UiBreadcrumbs` in a page's top bar; grey chevrons, last item current. |
| Boxes | `UiCard`, `UiPanel` (white, bordered, rounded-xl), `UiCallout` for notices |
| Empty states | `EmptyState`: icon, one sentence, one action |
| Dialogs | `UiModal` |

## Behaviour

- Row actions in tables: a quiet "Manage" or icon buttons (`ghost`, `sm`,
  `square`) at the end of the row; destructive ones confirm.
- Delete lives at the bottom of a settings sidebar or tab as a `subtle` button
  with a trash icon, never a red block button.
- Autosave text fields (title, description) with a status in the top bar;
  sharing and passwords save explicitly.
- A thing's management page lives at its own URL, not under the project
  section. It links to its project with a quiet chip or breadcrumb.
- Tabs split a management page by task (Overview, Versions, Sharing, Settings),
  like academic.page's site settings (Basics, Address, Custom domain).

## Checking

Check every page you touch in light, dark, and at 390px wide, with a real
browser (Playwright with the installed Chrome; scripts in `/tmp/fwtest/pw`).
Look at the screenshots before reporting. Run `npm run build` before sharing a
tunnel link.

## Refined navigation and actions

Use compact chevron breadcrumbs above the title, with an outline home icon. On artifact detail pages the title completes the trail, so the breadcrumb ends in a chevron and does not repeat the title. Use Artifacts consistently for artifact navigation. Include collection membership links in that trail; multiple collections are peer links, not a false hierarchy. Copy link uses a ghost button and a quiet outline copy icon. View is the primary action on artifact pages and keeps its filled accent style. Other secondary actions use ghost buttons by default.
