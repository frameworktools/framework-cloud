# Skills catalog cleanup

## Implementation

The catalog has one skills table, category pills, language filters, source selection, comma-separated tag links, and removable tag filters. Skill pages retain tag badges. Skill sets have the same category pills, one list, source selection, and members for the selected language. Visualization includes ggplot2 for R and matplotlib and seaborn for Python.

Cloud-only system skills carry versioned content, bundle files, and sync metadata. Package references require a bundle for the requested language. The three new package keys remain inline through `Skill::INLINE_PACKAGE_SKILLS` until released clients include them.

Added 11 general skills: cleaning data, checking data, regression models, model results, matplotlib publication figures, Stata publication graphs, report structure, Quarto, Jupyter, Git, and identifiable data. Data and modeling skills have R, Python, and Stata variants.

Added Framework extras, Sensitive data, Data cleaning, Modeling, Visualization, Writing, Reports, Quarto, Jupyter, and Git sets. Framework now has a Stata variant. The legacy ggplot2 set remains available. Quarto and Jupyter set keys end in `-skills` because facets share one key namespace with tools.

Standard Framework blueprints add Visualization and Writing. Derived defaults add the workflow skill and Git when enabled. Sensitive projects also get identifiable-data guidance. Explicit set selections and custom blueprints retain their chosen composition. AI off removes all skills. Standard R and Python projects have nine skills with Quarto or Jupyter and eight with scripts. Standard Stata projects have five.

Set forks preserve language layers and category. Editing one language preserves the others. Catalog summaries and the legacy R blueprint endpoint include language-layer members.

## Package sources

Updated sibling checkouts `../framework`, `../framework-py`, and `../framework-cli`. Framework titles identify package scope. Outputs now covers saving results. Caching, databases, and publishing have separate skills. Offline package upgrades install the new skills so existing guidance remains available. The ggplot2 skills no longer contain Framework-specific sections.

Regenerated skill histories, bundle snapshots, Python golden trees, and package conformance fixtures. Python shared assets are pinned to the uncommitted R working tree. Cloud's existing commit-pinned R snapshots remain intact; Cloud definitions extend those snapshots.

## Validation

- Cloud: 115 focused tests and 1,071 assertions passed, including resolver, skill sync, custom blueprint preservation, set editing, public blueprints, and preview conformance.
- JavaScript: three catalog-filter tests passed. All 55 skill texts and language variants passed frontmatter validation.
- Python: 114 creation, upgrade, defaults, and feature tests passed after reviewing the updated golden trees.
- R: focused skill, feature, project-creation, and upgrade checks passed.
- Package conformance: 37 Python checks and the focused R conformance checks passed.
- CLI: `go test ./internal/stata` passed.
- Created real temporary R, Python, and Stata projects from Cloud-resolved layers. All installed the inline Writing skill.
- Built frontend assets and checked both catalogs in light and dark mode at 1280px and 390px. Category filtering, expansion, browser errors, and horizontal overflow checks passed.
- Seeded the local database. Both existing custom blueprints retained their version IDs.

Preview: https://framework-cloud.pequod.dev/skills and https://framework-cloud.pequod.dev/skill-sets.

## Release state

No commits, pushes, production deployments, or package releases were made. Existing environment-file edits were preserved.

Package release work must commit the sibling source changes, synchronize Cloud's commit-pinned R definitions with `fw:sync-pieces`, and publish the package releases. Keep new keys inline while older clients remain supported. The catalog and defaults can ship with inline delivery before those releases.
