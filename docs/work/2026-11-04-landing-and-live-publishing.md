# Landing page and live publishing

Reworked the landing page around R, Python, the CLI, and research publishing.
The brand is Framework. The header has Cloud, R, Python, CLI, Docs, and
Self-hosting menus. Documentation links remain publicly accessible from local
previews. The hero has rotating code examples, language tabs, a file tree,
manual navigation, pause controls, and reduced-motion behavior. A three-step
walkthrough covers project creation, analysis, and publishing.

Tightened headline, code, and file-tree line heights. Each hero example has a
bold description above its language tabs. The subtitle names R and Python.

Browser checks passed in light and dark themes at 1440px and 390px. Checks
covered menus, keyboard dismissal, language switching, example selection,
project-file display, workflow tabs, overflow, automatic rotation, and pause.
Screenshots were inspected. Production assets were built. The focused Cloud
public-page and documentation tests passed: 49 tests and 240 assertions.

Live AWS S3 tests used temporary buckets and synthetic model representations.
R, Python, and the CLI delivered public and private bundles. Public readers
returned anonymous HTTP 200. Private readers returned anonymous HTTP 403 and
matched authenticated readback. Python versions 1 and 2 and retained archives
were verified. All temporary buckets and objects were deleted.

Live tests found and fixed R's scalar PolicyStatus parsing, missing AWS endpoint
fallbacks, and ACL-disabled retry. Uploads now retain the raw AWS response long
enough to inspect its error code. This avoids aws.s3 printing signing metadata.
R driver and publishing tests passed from the package checkout. The optional
S3_TEST_BUCKET fixture remains unset; these live checks ran separately.

The live GitHub Pages API rejected private publication for a private repository
without verified restricted Pages. Framework's organizations are on the Free
plan. Successful restricted Pages still requires an Enterprise Cloud test site.
No GitHub repository settings were changed.

Preview: https://framework-cloud.pequod.dev. Origin verified. Public routing
reaches Cloudflare Access. No commits, pushes, releases, or deployments.

## Workflow editor examples

Added shared escaped syntax highlighting for R, Python, YAML, TOML, and CLI
snippets. Each walkthrough panel has code on the left and project files on
the right. Language changes update file names and contents. Arrows remain in
project creation and publishing. The analysis panel has no arrow.

The analysis example opens dependency and helper files from the file pane.
R uses YAML packages installed by scaffold(). Python uses pyproject.toml and
uv. Its helper import follows scaffold() after the functions path is prepared.
Both examples call center_age() from a helper file, record the input hash on
read, and finish with data_verify(). The publishing copy emphasizes tracking
and sharing work with Framework Cloud. User wording replaced the walkthrough
introduction and the analysis heading is Conduct your analysis.

Executed both displayed analysis scripts in temporary projects with synthetic
CSV input. Helpers loaded, models and figures were created, initial hash checks
passed, and changes to the input were detected. Browser checks passed in light
and dark themes at desktop and 390px widths. Checked language-specific files,
helper and dependency navigation, highlighting, and page overflow.

## Browsable project and safeguards

Expanded the walkthrough to four stages: setup, analysis, settings and data,
and publishing. The analysis panel pins functions/data_prep.R or its Python
counterpart beside the script. The longer file list opens in a disclosure.
R settings demonstrate automatic theme_minimal application by scaffold().
Named catalog entries and environment-based database credentials have their
own settings example.

Added a shared read-only project editor. Every listed file opens highlighted
source, including actual bundled R/Python skill templates. Source payloads
are deduplicated across panels. The hero file view uses the same editor and
follows its R/Python selector. Synthetic CSV data and generated SVG, model
summary JSON, and HTML report are bundled under resources/examples/landing.

Added the requested AI and security sections immediately after the walkthrough.
They cover default skills, Cloud skill management, private/public folders,
ignore rules, optional pre-commit checks, hashes, changed-file warnings,
locked-file failures, verification, and metadata-only ledger sync. Replaced
the older duplicate AI and integrity sections. Local records store each
cataloged dataset's latest hash and read time; the page does not claim a
complete local history of every read. Git checks run at pre-commit and can
be bypassed. No claim of mandatory push enforcement is made.

Browser interaction checks covered every listed file in both languages,
helper panes, hero language synchronization, light/dark themes, and desktop
and 390px layouts. Focused public docs tests passed: 15 tests, 84 assertions.

Re-executed the displayed analysis code after the theme example changed. R's
active theme equals theme_minimal(), helpers load, and data_verify() detects a
modified CSV in both languages. Folder navigation is collapsible. Skill folders
start collapsed in the general project browser and expanded in the AI example.
Settings appear first in the setup examples; outputs appear first in publishing.

## Landing sequence and editor refinement

Moved setup options directly below the hero. ZIP download, GitHub clone, and
R/Python/CLI creation each show a small clickable project illustration.
Replaced the older repeated feature sections with the requested sequence:
setup, analysis and settings, AI, security, Framework Cloud, and publishing.
The Cloud section explains account defaults, blueprints, and Quarto controls,
with the generated _quarto.yml and related files available to inspect.
Publishing now closes the page.

Moved file navigation to the left. Each illustration includes only a few
relevant files followed by an ellipsis. Source lines wrap, file lists have no
scroll container, and long source documents use a thin dark vertical scrollbar.
The analysis and helper are stacked instead of squeezed into equal columns.
AI has a centered heading and full-width editor, with the included data skill
selected initially. Skill links use readable labels. Security has four brief
cards above a full-width editor.

The shared preview browser failed twice with a snapshot automation timeout.
Used installed Chrome for visual checks. File interaction, default skill,
section order, and horizontal overflow checks passed in light/dark at desktop
and 390px. Focused docs/self-hosting checks passed: 17 tests, 100 assertions.

## Explore Framework

Added a feature explorer after publishing, adapted from the supplied reference.
Thirteen topics cover setup, project types, the data catalog, integrity,
notebooks, packages, databases, environments, publishing, caching, AI agents,
security, and Cloud. Every topic includes manual code and Framework code for
both R and Python, with language-specific notes and documentation links.
R’s renv integration and Python’s uv workflow are described separately.

Desktop uses a vertical feature menu with keyboard navigation. Mobile uses a
feature selector. The R/Python choice persists between features. Source is
highlighted through the existing escaped renderer and deduplicated payload.

All 26 R snippets parse, and all 26 Python snippets pass AST parsing. Guide
paths exist in the corresponding package documentation sources. Chrome checks
covered all topics in both languages, selection persistence, keyboard and
mobile navigation, both themes, and overflow at 1440px and 390px. Screenshots
were inspected. Focused page tests passed: 17 tests and 100 assertions.
These are illustrative code snippets; dependency installation, Cloud writes,
and external database queries were not executed as part of this UI check.

Moved the ZIP/GitHub/tool setup options after the Cloud section and before
publishing, where the saved settings context is established. Centered the
workflow introduction and shortened it to “From setup to publication” with
one sentence about shared project conventions. Checked section order,
centering, and overflow in both themes at desktop and mobile widths.

Restored publishing as step 04 in the opening walkthrough. The full publishing
section follows the walkthrough. Data security and integrity now precede AI.
Verified the rendered order: workflow, publishing, data, AI, Cloud, setup
options, and feature explorer.

Moved the AI section directly after Framework Cloud. Replaced the longer
skills explanation with two sentences about the included R/Python skills and
sharing custom skills across projects. Added a compact editor mode for this
section, limiting source height to 16rem while retaining file navigation and
R/Python switching. Checked both themes at desktop and mobile widths.

## Preparation and Cloud grouping

The opening introduction now uses Erik’s exact sentence about turning an idea
into published research. Step 01 uses qualified framework::new_project() and
framework.new_project() calls with no import lines. The four steps are now
Scaffold a project, Prepare your project, Conduct your analysis, and Publish
your work. Preparation opens settings/packages.yml for R and pyproject.toml
for Python. Selecting settings, catalog, or connection files shows matching
usage code beneath the configuration. The skill file remains browsable.

Framework Cloud now follows the four steps. Publishing is nested inside that
section under “Publish and share artifacts with Framework Cloud.” Data
security follows Cloud. The project setup options are titled “Project
structures for your analysis” and show analysis, sensitive-data, course, and
presentation structures alongside the ZIP/GitHub/tool setup methods.

Browser checks cover package defaults and contextual code, qualified creation,
four-step ordering, nested publishing, project types, both themes, and mobile
widths. Focused page tests passed: 17 tests, 100 assertions.

## Concrete examples and project-type pills

Grouped related comments before their code. Package setup now shows
scripts/install-packages.R calling framework::packages_install(), and the
Python equivalent. Scaffold remains in session setup examples. Quarto/Jupyter
configuration moved into preparation preferences, with a browsable Quarto
settings file.

Reduced Cloud to three icon cards: Save shared defaults, Build and use
blueprints, and Publish your work. Its editor includes output files. Removed
the separate publishing subsection. Data security follows steps 01–04,
before Cloud. Security cards also have outline icons.

Replaced the security function list with an actual failed Git commit from a
temporary synthetic project. The private-folder ignore rule is commented out,
a private CSV is staged, and the enabled Framework pre-commit audit blocks
the commit. Verified that HEAD does not exist afterward. The page shows a
short output excerpt and lets visitors inspect the bad ignore rule and data.
No repository or external account was changed by the demonstration.

Project types are now interactive pills. Selecting analysis, sensitive data,
course, or presentation updates all three setup illustrations: ZIP name,
GitHub clone URL, R/Python/CLI creation command, and clickable example files.
File selection and language choices work across all four types. Checked both
themes at 1440px and 390px, with no page or code overflow. Focused page tests
passed: 17 tests, 100 assertions.
