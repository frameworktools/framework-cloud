# Publishing artifacts and collections

Publish a research output to give it a reader page and a link. An artifact can
be a rendered Quarto report or book, a figure, a table, a model summary, or a
file for readers to download. You can publish one output without publishing a
notebook or your project code.

Framework Public Cloud includes free publishing with 1 GB of artifact storage per account. All versions share this limit.
Self-hosted servers include publishing.

## Publishing destinations

Publish to Framework Cloud, GitHub Pages, a local or mounted folder, or object
storage. Named destinations are set in project settings:

```yaml
publish:
  default: lab
  destinations:
    lab:
      driver: folder
      path: /Volumes/lab/published
      visibility: private
```

Use `to="lab"` in R or Python, or `fw publish --to lab <file>`. Explicit
destinations override the project default. The account's Publishing settings
seed the destination for new projects.

Folder bundles include a reader page and retained versions. They work offline.
Folder access follows filesystem or web-server permissions. GitHub Pages sites
are public unless the destination API confirms restricted access. Private
repositories alone do not restrict Pages.

Cloud tracks external publication metadata when a project token is available.
It stores no external files or local paths. Access settings stay at the destination.

## Browser uploads

Open a project's **Artifacts** page and choose **Publish an artifact**, or use the
**Published** page. Select a file and enter an optional title and description.

For a rendered book or website, zip the contents of the output folder and select
**This is a zip of rendered HTML and its supporting files**. Enter the entry
page's path inside the zip, usually `index.html`. Upload rendered outputs rather
than the project folder, which may contain data, credentials, and source files.

An artifact's name identifies it within the project. Uploading with the same name
adds a version at the same link. Its display title can change independently.

Uploads currently allow up to 50 MiB. Rendered bundles may contain up to 4,000
entries and 200 MiB of expanded files.

## R, Python, and CLI publishing

From a project connected to Framework Cloud:

```r
framework::publish("outputs/figures/effect.png", to = "cloud", slug = "effect")
```

```python
import framework

framework.publish("outputs/figures/effect.png", to="cloud", slug="effect")
```

```sh
fw publish outputs/figures/effect.png --slug effect
```

The R and Python packages also accept rendered HTML, rendered directories, and
Quarto documents through their existing notebook publishing functions. The CLI
accepts rendered files and folders. Render source documents before uploading.

Explicit `to = "cloud"` in R or `to="cloud"` in Python selects Framework Cloud
even when the project has a storage bucket. Existing calls that omit `to` keep
their bucket-first behavior. Use `to = "bucket"` or `to="bucket"` for a bucket,
or `to = "github"` or `to="github"` for rendered content on GitHub Pages.

`publish()` accepts these objects in R and Python:

| Object | R | Python | Shown as |
|---|---|---|---|
| Data | data frames, tibbles, data.tables, matrices, `table` and `xtabs` | pandas and polars data frames and series, numpy arrays, crosstabs | A table with a profile of each column |
| Hypothesis tests | `t.test`, `chisq.test`, `wilcox.test`, `cor.test`, and other `htest` results | scipy.stats results such as `ttest_ind`, `pearsonr`, and `chi2_contingency` | The statistic, p-value, and confidence interval, with an interval chart |
| Regression models | `lm`, `glm`, `aov`, `glm.nb`, `polr`, `multinom`, lme4, `coxph`, `survreg`, fixest, and `mgcv::gam` | statsmodels results, including GLM, discrete models, MixedLM, and QuantReg | Coefficients, a forest plot, and fit statistics |
| ANOVA tables | `anova()` and `summary(aov)` | `anova_lm` | A table |
| Machine learning models | none | scikit-learn linear and tree models, xgboost, and lightgbm | Coefficients or feature importance, and the model's settings |
| Figures | ggplot and patchwork, base graphics from `recordPlot()`, and lattice | matplotlib and seaborn | An SVG image, or a PNG for very large plots |
| Interactive figures | plotly and other htmlwidgets | plotly figures and altair charts | The interactive figure |
| Formatted tables | gt, gtsummary, and flextable | pandas `Styler` | HTML |

For Cloud, data, tests, and models become a typed JSON representation. Tables
retain supplied rows, column names, types, and missing values, and each column
carries a summary computed over all its rows before publishing: missing values,
distinct values, summary statistics with a histogram for numbers, and the most
frequent values otherwise. Model summaries include coefficients, standard
errors, confidence intervals, aggregate fit statistics, and a formula when
available. The forest plot leaves out the intercept. For logistic, count, and
survival models it shows odds, rate, hazard, or time ratios on a log scale,
with a switch back to the model's own scale. Mixed models add their random
effects, and tree-based models show feature importance.

Data files publish too. A CSV or TSV becomes the same kind of table: Cloud
infers each column's type (numbers, dates, true/false values, or text, with
IDs such as `007` kept as text) and summarizes every column over all its rows.
Readers see the first 1,000 rows and can download the original file. Files
with more than 500,000 rows or 200 columns show a plain preview instead. Excel,
Parquet, Feather, Stata, SPSS, SAS, and RDS files are shared for download.

altair charts are drawn in an isolated frame that has no access to the rest of
Framework Cloud and can't fetch data from other sites, so a chart's data must be
inlined in its specification. altair inlines it when you publish.

Readers can sort columns, search cells, change pages, and export the loaded
preview as CSV. Numeric sorting respects numeric values and keeps missing
values last. Large integer identifiers are displayed as text to retain their
exact digits. The downloaded JSON retains the full representation. Table
previews load up to 1,000 rows and 50 columns. Notices identify omitted data
and other preview limits. Representations over 8 MiB remain downloadable.

Model summaries do not automatically upload the original model object or
observation-level data. Publishing a data frame uploads all the rows supplied
to that function. To share a saved model object, publish its file explicitly.
Readers can download it. Cloud does not execute it.

## Artifact management

Each artifact has its own page. Open it with **Manage** from **Published
outputs** or a project's **Outputs** tab. The page links back to its project
and has four tabs:

- **Overview** shows what readers see, with the artifact's size, views, and the
  collections and reports that use it.
- **Versions** lists every version with its own link, and deletes versions.
- **Sharing** sets who can open it, shows its links, and sets a password.
- **Settings** changes the title and description, merges another artifact in,
  and takes the artifact down.

## Links and passwords

New artifacts are **unlisted**. Anyone with their link can open them. Unlisted
links are not authenticated private access.

Enable a readable link on the artifact's **Sharing** tab, or use the `slug`
option from a package. A readable link looks like:

```text
https://framework.pub/@ada/nhanes-study/main-results
```

A readable link needs a handle, set in **Account > Account details**. The
unlisted link continues to work when readable links are turned off.

Add a password on the artifact's **Sharing** tab to require readers to unlock the page.
The password also gates originals, downloads, supporting assets, and earlier
versions. Passwords need at least eight characters. Changing a password revokes
existing reader sessions. Owners can open their artifacts while signed in.

Older artifacts may have files already exposed on a separate public storage
host. Cloud refuses to add a password while those external versions remain.
A password on the reader page cannot revoke a public file URL.

## Versions and merging

Each successful publish adds a version. The stable artifact link opens the
newest remaining version. A version-specific link identifies that exact version:

```text
https://framework.pub/d/q7w2k9m4x8c1v5b3n6z0p2rt/versions/2
```

Deleting a version takes down its links. Deleting the newest version makes the
previous one current. Version numbers are never reused. Deleting the only
version takes down the artifact.

On an artifact's **Settings** tab, use **Merge another artifact into this one** to
append another artifact's versions to the destination's history. Both artifacts
must belong to the same project. The source retains its original links and
versions, and future publishing uses the destination. The copied versions use
the destination's sharing settings. Merging combines version histories only.
The contents of two reports or statistical models are not combined.

## Collections

Open **Collections** within a project to create a collection. The title and
introduction save as you type. **Add artifact** searches the project's
published artifacts. Each entry's edit button sets its caption and version.
Drag an entry by its handle to reorder it, or focus the handle and use the up
and down arrow keys. An artifact can appear in several collections without
duplicating its files.

R, Python, and the CLI can attach a publication to an existing collection by ID
or slug using `collection` or `--collection`. To share an unlisted artifact
through an unlisted collection, explicitly pass `share_unlisted = TRUE`,
`share_unlisted=True`, or `--share-unlisted`. Collections are not created
automatically. If attachment fails after a successful publication, the client
returns the artifact URL and explains how to attach it from the web interface.

Each entry can follow the latest artifact version or pin an existing version.
Pin versions when you want reviewers to read a particular set of results.
A deleted pinned version becomes unavailable. Its entry does not move to
another version.

Collections start **private**, visible only to their owner. You can share them
as **unlisted** or **public** and optionally set a collection password.
Sharing changes apply when you choose **Save sharing**.

A shared collection's link can expire after a day, a week, 30 or 90 days, or
on a date you choose. It works through the end of that day in your time zone.
After that, readers see a page saying the link has expired. You can still
open the collection, and clearing the expiry restores the link.
Public collections require public artifacts. Sharing an unlisted artifact in
an unlisted collection requires explicit consent for that entry.

A collection's password protects its listing. Individual artifacts retain
their own access rules. Protect an artifact itself if its direct link and files
also need a password. Unlocking a collection never unlocks its artifacts.

A collection can organize materials for collaboration, peer review, or
publication. It does not currently provide reviewer accounts, comments, DOI
registration, or an archival guarantee.

## Report builder

Open **Reports** within a project and create a report. The editor shows the
report as readers will see it: a title, an introduction, and sections of text
and artifacts in order.

- **Text.** The toolbar at the top of the report formats whichever text
  section you are writing in: headings, bold, italic, underline,
  strikethrough, inline code, links, bulleted and numbered lists, and quotes.
  **More** adds superscript and subscript (for R² or H₂O), code blocks,
  dividers, and clearing formatting. Selecting text also shows a small menu
  for the common marks. Markdown works as you type: `##` starts a heading,
  `-` a list, `1.` a numbered list, `>` a quote, and `**bold**` or
  `*italic*` format text. Quotes and dashes become typographic ones.
- **Artifacts.** **Add artifact** opens a search of the project's published
  figures, tables, models, tests, and documents. Each artifact section shows a
  live preview, a version menu, and a caption.
- **Section order.** Hover between sections to insert text or an artifact there.
  Drag a section by the handle in its left margin, or focus the handle and
  use the up and down arrow keys. The right margin has buttons to move,
  duplicate, and remove a section. The outline beside the report jumps to
  any section.

| Shortcut | Mac | Windows and Linux |
|---|---|---|
| Bold, italic, underline | ⌘B, ⌘I, ⌘U | Ctrl+B, Ctrl+I, Ctrl+U |
| Link | ⌘K | Ctrl+K |
| Heading, subheading, body text | ⌘⌥2, ⌘⌥3, ⌘⌥0 | Ctrl+Alt+2, Ctrl+Alt+3, Ctrl+Alt+0 |
| Bulleted and numbered list | ⌘⇧8, ⌘⇧7 | Ctrl+Shift+8, Ctrl+Shift+7 |
| Save now | ⌘S | Ctrl+S |

Drafts save automatically a moment after you stop typing. Adding or removing
a password applies only when you choose **Save draft**.
Use **Preview** to read the saved draft as readers will.

Saving a draft updates the editor. Publishing creates a numbered report
version containing the current text, introduction, captions, and selected
artifact versions. When an artifact section follows the latest version in
the draft, publishing pins the version available at that moment. Artifact
labels and kinds are also preserved. Later changes to the analysis or draft
do not alter an existing published report.

Reports start private. You can publish them as unlisted or public and set a
report password. Public reports require public artifacts. Unlisted artifacts
require explicit permission to share them with report readers. Artifact
passwords continue to apply separately, including inside a report.

Changing a report password revokes existing report reader sessions. Leaving
its replacement field blank keeps the current password. Select the explicit
removal option to remove protection. A removed artifact version shows as
unavailable in older reports, without substituting a newer result.

The report reader provides section navigation, formatted text, interactive
artifact displays, download links, and access to earlier report versions.
Hosted report publishing is free.

### Reports from a script or an AI agent

The reports API builds the same reports from R, Python, Stata, or a terminal.
Requests go to `/api/v1/projects/<project>/reports` with the project token from
`.env` (`FW_PROJECT_TOKEN`) or a personal API token.

| Action | Request |
|---|---|
| List reports | `GET /api/v1/projects/<project>/reports` |
| Read one | `GET /api/v1/projects/<project>/reports/<report>` |
| Create | `POST /api/v1/projects/<project>/reports` |
| Update the draft | `PATCH /api/v1/projects/<project>/reports/<report>` |
| Publish | `POST /api/v1/projects/<project>/reports/<report>/publish` |

A report is a list of `blocks`. Text blocks are Markdown, or HTML with
`"format": "html"`, cleaned the same way as in the editor. Artifact blocks name
a published artifact by its slug:

```json
{
  "title": "Penguin body mass",
  "publish": true,
  "blocks": [
    {"type": "text", "text": "## Body mass by species\n\nGentoo penguins are heaviest."},
    {"type": "artifact", "artifact": "mass-by-species"},
    {"type": "artifact", "artifact": "penguin-body-mass", "caption": "Linear model"}
  ]
}
```

Send `markdown` instead of `blocks` for a single text block. `publish: true`
publishes after saving. The response includes the report's link and its
versions. The **Creating Framework reports** skill teaches an AI agent to use
this API.

## Storage

New artifact files live on a private filesystem disk and are served through
Framework Cloud. Metadata, version histories, collections, and password hashes
live in the database. See [Server setup](server-setup.md#published-artifacts)
for the private storage configuration and backup requirements.
