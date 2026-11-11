# Blueprint editor

## Layout

Each editor section has a `?tab=` URL and displays one section. Inertia navigation preserves unsaved form values and resets scroll position. Validation opens the section containing the first invalid field.

Removed outer form cards and Cancel. The editor uses the available content width. Labels use “Blueprint” and “AI Agents.”

Skills use a left rail of actual skill sets, starting with All. The separate skill-set selector and Selected only controls are removed. A separate set chooser populates the individual list. The rail filters individual items by theme. A header checkbox selects all visible items when none are selected. With any visible selections, it opens a Remove all confirmation and clears only visible items after confirmation. Individual skills remain selectable, including skills supplied by an existing set. Packages follow the same set-then-refine workflow, including individual additions and removals across overlapping sets. On narrow screens the rail scrolls horizontally.

Rows toggle from their description or empty space. Labels and checkboxes toggle once. Expanding package contents does not change the selection. Shared checkboxes have checked, disabled, focus, and dark-mode styling.

## Selection behavior

Set membership stays in the saved composition, preserving language-specific members. Individual removals are submitted as `excluded_skills` and stored as null skill overrides. Catalog and project previews distinguish positive selections from removals. Choosing a set clears previous exclusions for its members. Individual package overrides save catalog definitions for additions and null entries for removals. Bulk changes use one model update to avoid stale controlled props.

## Validation

- Frontend build passed.
- 37 focused blueprint, project-builder, resolver API, and skill-catalog tests passed with 561 assertions.
- Browser checks passed for section navigation, selection persistence, category filtering, Add all, individual removals, whole-row selection, checkbox toggling, and package expansion.
- Checked desktop and 390px layouts in light and dark mode. No page overflow or application console errors were found. The third-party analytics beacon has a certificate error in the preview browser.

Preview: https://framework-cloud.pequod.dev/blueprints/create?tab=ai

Changes remain local and uncommitted.

## Blueprint sections and single-language choices

The editor now starts with Basics, then Language, Notebook format, Project structure, Environment reproducibility, Packages, AI Agents, and Git. Name, description, visibility, and advanced YAML live in Basics. Outer section cards and the preview workflow selector are removed.

New and copied personal blueprints pin one language and one workflow. Legacy personal blueprints read as one language and workflow without rewriting pinned versions. Framework's built-in type templates remain shared between language catalogs. R supports Quarto, R Markdown, and Jupyter; Python supports Quarto and Jupyter. Stata automatically uses scripts. Language changes remove incompatible structure and piece choices.

Notebook features live under Notebook format. Environment reproducibility offers renv for R and uv for Python. Targets is removed from the builder and defaults UI. Personal tool defaults no longer turn off a personal blueprint's chosen environment. A selected notebook format overrides the personal R Markdown default.

Settings is replaced by Git, with Git initialization and the supported commit hooks. Sensitive-data behavior comes from the structure. Hook choices round-trip, explicit deselections override personal defaults, and Git-off or Stata suppresses hooks.

Additional verification: 106 focused blueprint, project, API resolution, hosted MCP, and public-blueprint tests passed (1,573 assertions). Browser checks verified theme-scoped confirmation, cancellation, clearing, selecting, package-set population, and preserving hidden selections. Checked the 390px layout in light and dark mode with no overflow. Stata shows no notebook picker; R and Python show their own formats and environment tools.

## Collapsible set browsers and agent setup

Package and skill sets now use the same collapsible browser. A wider left column contains set names and selection checkboxes. Clicking a name previews its contents on the right without selecting it. Search and fixed tag filters narrow the set list. Collapsing the browser preserves selections and displays their count. Individual refinement remains below.

Package set filters separate ecosystem metadata (Tidyverse, pandas, Polars) from five work themes: Data management, Statistical modeling, Notebooks, Apps, and Data visualization. Filters are single-select across ecosystems and themes. Selecting another filter replaces the previous one; All clears the filter. Skill sets use the existing shared, language-neutral skill themes. The All filter sits inline with the other filters. All filters share typography and spacing; the stray Ecosystems caption and repeated package instructions are removed.

AI Agents and AI Skills are separate tabs. AI Agents selects Claude Code, Codex, GitHub Copilot, Other, or None and explains the generated instruction files. AI Skills contains the set browser and individual checklist. Agent choices are stored as versioned, enum-validated agent_defaults metadata rather than arbitrary layer settings. They apply during project resolution without changing account preferences. Raw blueprint YAML still cannot set personal agent preferences. None disables agent files and skills.

The local migration adding blueprint_versions.agent_defaults was applied. The frontend build and 83 focused tests passed (1,405 assertions), including agent preference round trips, previews, account-default preservation, resolution policy, and hosted MCP behavior. Browser checks verified independent preview and selection, tag filtering, package population, collapsing, inline All alignment, and separate agent/skill tabs. Desktop and 390px layouts have no horizontal page overflow. Light and dark layouts were inspected.

Preview: https://framework-cloud.pequod.dev/blueprints/create?tab=skills

Changes remain local and uncommitted.

Bulk-checkbox behavior revised: empty selects all visible items immediately; mixed opens Select all confirmation; fully checked clears visible items immediately. The shared checkbox restores its controlled checked and indeterminate state after change events, including while a confirmation is pending.

Individual package and skill lists now default to Selected at the top of the theme rail. Selected includes set members and direct additions, respects exclusions, and updates its count as choices change. All is last. Empty selected views point to the set chooser and theme filters. Bulk operations retain the visible-row snapshot while selections change.

## R package auto-attach

The individual package checklist now has Auto-attach switches for R. Switches inherit selected package-set flags, are disabled for unselected packages, and do not change package selection. Explicit flags round-trip through blueprint saving, project previews, and API resolution. Turning off auto-attach preserves installation. Deselected packages are never restored by remembered switches. Flag-only overrides remain inherited selections when the editor reopens. A personal blueprint's explicit package entries take precedence over conflicting account package defaults.

Python and Stata do not expose the switches, and the resolver rejects nonempty auto-attach maps for those languages. The package and scaffold guides now state that auto-attach uses library() in R, Python requires explicit imports, and Stata has no equivalent option.

Frontend build passed. The initial 91 focused blueprint, project, resolver API, and MCP tests passed; follow-up checks passed after testing account-default precedence and flag-only selection round trips. Browser checks verified inherited switch values, independent switch toggling, stable selection counts, and hiding controls for Python.
