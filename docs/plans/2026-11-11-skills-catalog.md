# Skills catalog cleanup

Status: implemented in local source (2026-11-11). Production deployment and package releases are pending. See `docs/work/2026-11-11-skills-catalog.md` for validation and release notes.

## Current state

Built-in skills (12):

| Key | Title | Source | Languages | Notes |
|-----|-------|--------|-----------|-------|
| framework-workflow | Notebooks and scripts | R and Python packages | R, Python | scaffold() rules plus make_notebook()/make_script() |
| framework-data | Reading and saving data | packages | R, Python | data_read()/data_save(), catalog, folders |
| framework-packages | Managing packages | packages | R, Python | package_add(), auto-attach, renv |
| framework-outputs | Saving outputs and publishing | packages | R, Python | save_*(), cache_remember(), db_query(), publish(): four topics |
| framework-sensitive-data | Handling sensitive data | packages | R, Python, Stata | private/ and public/ folder rules |
| framework-stata | (Stata) | Stata package | Stata | everything Framework for Stata in one skill |
| framework-reports | Creating Framework reports | cloud only | all | Framework Cloud reports API |
| ggplot2-exploring | Exploring data with ggplot2 | R package | R | ends with "In a Framework project" |
| ggplot2-publishing | Publication figures with ggplot2 | R package | R | same |
| matplotlib-exploring | Exploring data with matplotlib | cloud only | Python | no publishing counterpart |
| stata-graphs | Exploring data with Stata graphs | cloud only | Stata | no publishing counterpart |
| writing | Writing | cloud only | all | prose only |

Skill sets (2): `framework` (the four framework-* skills) and `ggplot2` (R only).
Both are defined in the R repo (`inst/sets/skills`) and synced into the cloud.

Built-in blueprints attach only Framework skills, plus ggplot2 for types that
have the Framework set. Writing, reports, matplotlib, and Stata graphs are in
no blueprint, so no new project gets them unless someone adds them by hand.

Erik's own skills (10, public, used by the Yale course blueprints): analysis-report,
data-cleaning, ggplot-style, git, notebooks, outputs, packages, protected-data,
regression-modeling, viz-house-style. Several are general skills written in
course voice ("in this project").

Catalog page: language tabs, a search box, a source select, a row of tag
buttons, then one table per source (Yours, From Framework, Shared by others).
Tags are underlined text buttons. With 19 R skills the page is a long list
split by who wrote it, which is the least useful split.

## Problems

1. **Framework skills don't say they are Framework-only.** "Reading and saving
   data" reads like general advice. An agent or a person browsing can't tell it
   only applies when the Framework package is in use.
2. **Some skills are not atomic.** framework-outputs covers four unrelated
   jobs. framework-workflow mixes the scaffold() rules with creating files.
   The ggplot2 skills carry a Framework section.
3. **Coverage is uneven.** R has exploring and publishing figures; Python and
   Stata only have exploring. There are no general skills for data cleaning,
   modeling, Git, Quarto, Jupyter, or report structure, though Erik's course
   skills show the demand.
4. **Sets are thin and R-shaped.** Two sets, one R only, both defined in the
   R repo.
7. **Cloud-only built-ins can't be installed.** `Skill::layerEntry()` gives
   every system skill `{package: true}` (app/Models/Skill.php:94), and the R
   package rejects a package skill it doesn't ship (framework R/spec.R:198).
   Adding writing, framework-reports, matplotlib-exploring, or stata-graphs to
   a blueprint today breaks project creation. The package already installs
   inline cloud content (R/spec_create.R:303), so the fix is delivery, not
   package support.
5. **Blueprints don't use what we have.** Shipping more skills does nothing if
   no blueprint attaches them.
6. **The catalog is grouped by author.** The first question people have is
   "what is there for X", not "who wrote it".

## Proposal

### 1. Delivery fix (first, and on its own)

System skills that no package ships are delivered like user skills: inline
versioned content and bundle files, with sync metadata. Only skills in
`skill-bundles.json` (package-shipped) use `{package: true}`. Test: a blueprint
with writing resolves and creates an R, a Python, and a Stata project.

### 2. Naming

Skills that need a Framework package get titles starting "Framework: ". The
cloud API skill starts "Framework Cloud: ". General skills have plain titles.
Keys don't change: projects' skill locks, upgrade(), and skill-history.json
track package skills by key.

| Key | New title |
|-----|-----------|
| framework-workflow | Framework: notebooks and scripts |
| framework-data | Framework: reading and saving data |
| framework-packages | Framework: package management |
| framework-outputs | Framework: saving outputs |
| framework-sensitive-data | Framework: sensitive data |
| framework-stata | Framework for Stata |
| framework-reports | Framework Cloud: publishing reports |

Descriptions keep their task triggers and say "in a project that uses the
Framework package". Package text changes go through all three sources (R
package, framework-py, framework-cli for Stata), `data-raw/skill_history.R`
in both R and Python, `scripts/sync-skill-bundles.py`, then `fw:sync-pieces`.
The hard-coded AGENTS.md descriptions in R/ai_skills.R:200 and
framework-py's _agents_md.py change with them.

### 3. Atomic skills

Split framework-outputs into `framework-outputs` (save_table, save_figure,
save_model), `framework-cache`, `framework-databases`, and
`framework-publishing`. This is package work: the default skill list used by
upgrade() (framework R/upgrade.R:922) and framework-py's defaults.yml gain the
new keys, and upgrade() installs them beside the trimmed outputs skill. The
ggplot2 skills lose their "In a Framework project" sections.

New general built-ins. Keys avoid Erik's course keys, because project
resolution stores skills under the bare key (app/Support/Resolver.php:376) and
`framework/data-cleaning` and `@erik/data-cleaning` could not both install.

| Key | Title | Category | Languages |
|-----|-------|----------|-----------|
| cleaning-data | Cleaning data | Data cleaning | all, with variants |
| checking-data | Checking data | Data cleaning | all, with variants |
| regression-models | Fitting regression models | Modeling | all, with variants |
| model-results | Reporting model results | Modeling | all, with variants |
| matplotlib-publishing | Publication figures with matplotlib | Visualization | Python |
| stata-graphs-publishing | Publication graphs in Stata | Visualization | Stata |
| report-structure | Structuring an analysis report | Reports | all |
| quarto-documents | Writing Quarto documents | Quarto | R, Python |
| jupyter-notebooks | Working in Jupyter notebooks | Jupyter | R, Python |
| git-workflow | Using Git in an analysis project | Git | all |
| identifiable-data | Working with identifiable data | Privacy | all |

Boundaries:

- writing: how sentences read. report-structure: a report's sections and what
  goes in each. framework-reports: the API that publishes one.
- cleaning-data: turning raw data into analysis data and recording each
  decision. checking-data: assertions and summaries that prove the data is
  what the analysis assumes.
- regression-models: choosing, fitting, and diagnosing. model-results:
  tables of estimates, marginal effects, and model plots.
- identifiable-data: behavior with identifiable data (never print rows,
  suppress small cells). framework-sensitive-data: where Framework keeps
  those files.

Erik's course skills stay as they are. Regression test: every Yale blueprint
resolves to the same skill keys and folders before and after.

### 4. Categories

A fixed, ordered list in code (`SkillTags::CATEGORIES`), each mapped to an
existing or new tag: Framework, Data cleaning, Modeling, Visualization,
Writing, Reports, Quarto, Jupyter, Git, Privacy. No `is_category` column. A
skill is in a category when it has that tag. Sets use the existing
`facets.category` field with the same keys. The seeder re-syncs built-in tags
on every run (today it only tags new skills, BlueprintSeeder.php:168).

### 5. Skill sets

Every requested area is a set, including one-skill sets: project defaults
store skill sets, not single skills (app/Support/ProjectDefaults.php:117), so
a one-skill set is how a skill becomes a default.

| Set | R | Python | Stata |
|-----|---|--------|-------|
| Framework | workflow, data, packages, outputs | same | framework-stata |
| Framework extras | cache, databases, publishing | same | none |
| Sensitive data | framework-sensitive-data, identifiable-data | same | same |
| Data cleaning | cleaning-data, checking-data | same | same |
| Modeling | regression-models, model-results | same | same |
| Visualization | ggplot2-exploring, ggplot2-publishing | matplotlib-exploring, matplotlib-publishing | stata-graphs, stata-graphs-publishing |
| Writing | writing | same | same |
| Reports | report-structure, framework-reports | same | same |
| Quarto | quarto-documents | same | none |
| Jupyter | jupyter-notebooks | same | none |
| Git | git-workflow | same | same |

Visualization uses `language_layers` (resolution already handles them,
app/Support/ProjectLayers.php:138). The skill sets page and the API listing
read only `layer.skills` (FacetsController.php:61, Api/V1/PiecesController.php:289)
and must become language-aware. `ggplot2` stays as it is for compatibility;
Visualization is a new set.

Sets with cloud-only members are defined in the cloud
(`database/seeders/masters/sets/skills`). The R repo keeps Framework and
ggplot2.

Offline creation, by case:
- Cached cloud defaults keep cloud skill content and work offline as now
  (framework R/cloud_resolve.R:107).
- An explicit cloud-only set name offline fails as an unknown set
  (R/features.R:95). Change the message to say the set needs Framework Cloud.
- Signed out: package skills only, as today.

### 6. Blueprints

Defaults stay near ten skills. Every installed skill adds an AGENTS.md row and
a description the agent reads.

| Blueprint | Sets | Count (R) |
|-----------|------|-----------|
| Standard | Framework, Visualization, Writing, Quarto or Jupyter (follows the workflow), Git when Git is on | 9 |
| Privacy sensitive | Standard's, plus Sensitive data | 11 |
| Presentation | Framework, Visualization, Writing, Quarto | 8 |
| Course | Framework, Visualization, Writing, Quarto or Jupyter, Git | 9 |
| Shiny, Plumber, Shiny for Python, FastAPI | framework-workflow and framework-packages (as now), Git | 3 |
| Bare | Git when on | 0 or 1 |

Data cleaning, Modeling, Reports, and Framework extras are opt-in. The
Quarto, Jupyter, and Git attachments are explicit resolver rules, because no
mechanism derives sets from workflows or settings today
(Resolver.php:199, 369). Turning AI files off removes these skills too
(Resolver.php:417 only strips sets).

### 7. Catalog page

- Language stays the first control.
- Category pills under it: All (default), then each category with its count
  for the current language. In the URL: `/skills?category=modeling`.
- One table. Columns: Skill (name, summary, a "Yours" badge on your own),
  Tags as plain comma-separated links, actions.
- Source becomes a select (All, Yours, Framework, Shared). The tag button row
  goes; a tag chosen from the table shows as a removable badge by the search.
- Skill sets page: same pills, using `facets.category`.
- Skill pages: tags as badges (done in 7e76adf).

### 8. Order of work

1. Delivery fix for cloud-only built-ins, with tests. Ship alone.
2. Catalog page, categories, comma tags, tag re-sync. Cloud only.
3. New cloud skills and cloud-defined sets; language-aware set listings.
4. Blueprint defaults and the Quarto, Jupyter, and Git rules; Yale blueprint
   regression tests; conformance fixtures.
5. Package skill titles and descriptions: R, Python, Stata (framework-cli),
   skill history, bundle sync, releases.
6. framework-outputs split, last. Cloud defaults reference the new keys only
   after the releases ship, because older installed packages don't have them.

## Risks

- **Older packages.** A default that names a package skill an installed
  package lacks breaks creation. New package keys stay out of defaults until
  the release that ships them is required.
- **Course blueprints.** Covered by the key choices and the regression test.
- **Category drift.** The list is in code. Adding one is a code change.
