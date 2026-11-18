# Publishing artifacts

Publish a research output to give it a reader page and a shareable link. An artifact can be a rendered Quarto document or book, a figure, a table, a hypothesis test, a model summary, or a downloadable file. Individual outputs can be published without uploading notebooks or project source code.

Framework Public Cloud includes 1 GB of artifact storage per account across all saved versions, including versions in Trash. Self-hosted servers include artifact publishing for every account and have no storage limit unless an administrator sets `FW_PUBLISH_STORAGE_LIMIT_BYTES`. See [Configuration reference](configuration.md#server).

## Destinations

Framework publishes to Framework Cloud, GitHub Pages, local or mounted folders, and S3-compatible object storage. Configure named destinations in `settings.yml`:

```yaml title="settings.yml"
publish:
  default: lab
  destinations:
    lab:
      driver: folder
      path: /Volumes/lab/published
      visibility: private
```

Select a destination with `to = "lab"` in R, `to="lab"` in Python, or `fw publish --to lab <file>`. Explicit destinations override the project default. Your blueprint's **Publishing** choice sets the initial default for new projects.

Folder publications write a static reader bundle and version history that work offline under filesystem or web-server permissions. GitHub Pages sites are public unless GitHub Enterprise access controls restrict them. A private GitHub repository alone does not restrict GitHub Pages.

When a project token is configured, Framework Cloud records metadata for external publications without storing external files or local filesystem paths. Access rules remain governed by the destination.

## Browser uploads

Open **Artifacts** in the sidebar or a project's **Artifacts** tab and select **Publish an artifact**. Choose a file and enter an optional title and description. Under **Sharing**, choose **Private, only you**, **Anyone with the link** (the default), or **Public, with a readable link**. An optional password of at least eight characters protects the artifact from the start. A new version keeps the current password when the field is blank.

For a multi-file HTML output such as a Quarto book or website, compress the rendered output directory into a `.zip` archive and select **HTML ZIP bundle**. Enter the entry HTML path inside the archive in **ZIP entry page**, such as `index.html`. Upload only rendered output directories rather than the project root, which may contain raw data, credentials, and source scripts.

An artifact's slug identifies it within its project. Uploading under an existing slug appends a new version at the same URL while letting you update the display title independently.

Single-file uploads accept up to 50 MiB. Rendered `.zip` bundles accept up to 50 MiB compressed, 4,000 files, and 200 MiB uncompressed.

## R, Python, and CLI publishing

Publish from a connected project in R, Python, or a terminal:

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

The R and Python packages accept in-memory objects, files, rendered HTML, rendered directories, and Quarto documents. The `fw` CLI uploads rendered files and directories, so render source notebooks before calling `fw publish`.

Passing `to = "cloud"` in R or `to="cloud"` in Python targets Framework Cloud even when the project defines an object-storage bucket. Calls that omit `to` follow the project's default destination, or a configured bucket when the project sets no default. Pass `to = "bucket"` for object storage or `to = "github"` for GitHub Pages.

`publish()` serializes these in-memory objects in R and Python:

| Object | R | Python | Shown as |
|---|---|---|---|
| Data | data frames, tibbles, data.tables, matrices, `table`, and `xtabs` | pandas and Polars data frames and series, NumPy arrays, and crosstabs | Interactive table with column profiles |
| Hypothesis tests | `t.test`, `chisq.test`, `wilcox.test`, `cor.test`, and other `htest` results | `scipy.stats` results including `ttest_ind`, `pearsonr`, and `chi2_contingency` | Test statistic, p-value, confidence interval, and interval plot |
| Regression models | `lm`, `glm`, `aov`, `glm.nb`, `polr`, `multinom`, `lme4`, `coxph`, `survreg`, `fixest`, and `mgcv::gam` | `statsmodels` results including GLM, discrete choice, MixedLM, and QuantReg | Coefficient table, forest plot, and fit statistics |
| ANOVA tables | `anova()` and `summary(aov)` | `anova_lm` | Summary table |
| Machine learning models | none | `scikit-learn` linear and tree models, `xgboost`, and `lightgbm` | Coefficients or feature importance and model parameters |
| Figures | `ggplot2`, `patchwork`, `recordPlot()` base graphics, and `lattice` | Matplotlib and Seaborn | SVG image, or PNG for dense plots |
| Interactive figures | Plotly and other `htmlwidgets` | Plotly figures and Altair charts | Sandboxed interactive chart |
| Formatted tables | `gt`, `gtsummary`, and `flextable` | pandas `Styler` | Rendered HTML table |

Data frames, hypothesis tests, and models upload as typed `framework.artifact/v1` JSON representations. Table artifacts store the supplied rows, column names, types, and missing-value markers along with full-column summaries: missing counts, distinct counts, numeric summary statistics with histograms, and top category frequencies. Model summaries store coefficients, standard errors, confidence intervals, fit statistics, and model formulas when available. Forest plots omit the intercept. Logistic, count, and survival models display odds, rate, hazard, or time ratios on a log scale with a toggle for the linear predictor scale. Mixed models include random-effects variance components and tree models display feature importance.

Bayesian models publish posterior summaries, never their draws: brms and rstanarm fits in R, Bambi models and ArviZ `InferenceData` in Python (`publish(idata, model=bambi_model)`), and Stata's `bayes:` prefix and `bayesmh`. The artifact shows the posterior mean and SD, the 95% credible interval, R-hat, and effective sample sizes. Odds and rate ratios are summaries of the exponentiated draws. A warning appears when R-hat exceeds 1.01 or the sampler reported divergent transitions, and the model summary lists the priors, chains, draws, warmup, and sampler. bayesplot and ArviZ plots publish as figures.

Uploading a CSV or TSV file builds the same interactive table view. Framework Cloud infers numeric, date, boolean, and text columns while preserving leading-zero identifiers such as `007` as text. It then profiles every column across all rows. Files exceeding 500,000 rows or 200 columns render a plain preview. Excel, Parquet, Feather, Stata, SPSS, SAS, and RDS files publish as downloadable files.

Altair charts render inside a sandboxed frame isolated from Framework Cloud cookies and external network requests. Chart specifications must inline their data, which Altair does by default during `publish()`.

Interactive table readers support sorting, cell search, pagination, and CSV export of loaded preview rows. Numeric sorting orders numeric values and places missing values last. Large integers render as text to preserve exact digits. Table previews load up to 1,000 rows and 50 columns and display notices for omitted rows or columns. JSON downloads and files over 8 MiB remain downloadable in full.

Publishing a model summary uploads summary statistics rather than observation-level training data or serialized model binaries. Publishing a data frame uploads every row passed to `publish()`. To share a serialized model file, publish that file directly for download. Framework Cloud never executes uploaded model files.

## Artifact management

Open an artifact's management page from **Artifacts** in the sidebar or from a project's **Artifacts** tab. Each artifact page links to its project and provides four tabs:

- **Overview** previews the reader view alongside file size, view counts, and linked collections or reports.
- **Versions** lists every version with individual links and deletion controls.
- **Sharing** configures visibility, readable URLs, and password protection.
- **Settings** edits the title and description, links additional projects, merges version histories, and moves the artifact to Trash.

Restore a trashed artifact from **Trash** in the sidebar. Items in Trash are deleted permanently after 30 days, files included. Self-hosted servers set this period with `FW_TRASH_DAYS`.

## Links and passwords

Browser uploads and package or CLI `publish()` calls create **unlisted** artifacts by default, while artifacts created by AI agents or direct `/api/v1/artifacts` uploads start **private**. Anyone with an unlisted artifact's random link can view it, whereas private artifacts are visible only to their owner while signed in.

Enable a readable URL by choosing **Public** on the **Sharing** tab or passing `visibility = "public"` when publishing. Readable URLs use your account handle and project slug:

```text title="Readable URL"
https://framework.pub/@ada/nhanes-study/main-results
```

Set your account handle in **Account > Account details**. Disabling the readable URL leaves the random unlisted URL active when visibility remains unlisted.

Set a password on the **Sharing** tab to require authentication before opening the reader page, original files, downloads, supporting assets, or historical versions. Passwords require at least eight characters. Updating a password invalidates existing reader sessions, while signed-in owners bypass the password prompt.

Public and unlisted artifacts stored on Framework Cloud without a password can be embedded on other websites. See [Embedding artifacts](embedding.md).

Legacy artifacts stored on a public object-storage domain cannot add a password while public bucket versions remain, because a reader-page password cannot revoke direct bucket URLs.

## Versions and merging

Publishing again under the same slug appends a numbered version. The primary artifact URL serves the latest active version, while version URLs address a specific snapshot:

```text title="Version URL"
https://framework.pub/d/q7w2k9m4x8c1v5b3n6z0p2rt/versions/2
```

Deleting a version disables its URL. Deleting the latest version promotes the preceding version to current. Version numbers are never reused. Deleting the sole remaining version takes down the artifact.

On an artifact's **Settings** tab, **Artifact merging** appends another artifact's versions from the same project when you select a source artifact and click **Merge versions**. The source artifact keeps its own links and versions, while future uploads target the destination artifact and merged versions inherit the destination's sharing settings. Merging combines version histories without combining document text or model estimates.

## Collections

Open **Collections** to create a collection inside a project or across your account. Titles and introductions save automatically as you edit. Select **Add artifact** to search artifacts from the collection's project or from your entire account when the collection has no project. Edit an entry to set its caption and version selection. Reorder entries by dragging the handle or pressing the Up and Down arrow keys while focused on the handle. Referencing an artifact in multiple collections does not duplicate stored files.

R, Python, and `fw` can attach a published artifact to an existing collection by ID or slug with `collection` or `--collection`. Attaching an unlisted artifact to an unlisted collection requires `share_unlisted = TRUE` in R, `share_unlisted=True` in Python, or `--share-unlisted` in `fw`. Clients do not create collections automatically. When attachment fails after upload, the command prints the artifact URL and web instructions.

Each collection entry either tracks the latest version or pins a fixed version number. If a pinned version is deleted, the collection entry displays as unavailable rather than advancing to another version.

Collections start **private**. Change visibility to **unlisted** or **public**, set an optional password, and click **Save sharing**. Shared collection links can also expire after 1, 7, 30, or 90 days or at the end of a chosen calendar date in your time zone. Clearing the expiration date re-enables the link. Public collections require public artifacts and unlisted collections require explicit confirmation per unlisted artifact.

A collection password protects only the collection listing page. Individual artifacts enforce their own visibility and passwords independently. Unlocking a collection does not unlock password-protected artifacts inside it.

Collections organize outputs for collaborators or peer reviewers without providing reviewer accounts, inline comments, DOI registration, or archival preservation guarantees.

## Report builder

Open **Reports** to create a report in a project or with no project. Project reports can embed that project's own and linked artifacts, while account-level reports can embed any artifact you own. The editor displays the title, introduction, and ordered text and artifact blocks:

- **Text.** Format headings, bold, italic, underline, strikethrough, inline code, links, lists, quotes, superscripts, subscripts, code blocks, and dividers from the top toolbar or selection menu. Standard Markdown shortcuts (`##`, `-`, `1.`, `>`, `**bold**`, and `*italic*`) convert as you type.
- **Artifacts.** Select **Add artifact** to insert published figures, tables, models, tests, or documents with a live preview, version selector, and caption.
- **Section order.** Insert blocks between sections, drag left-margin handles, use the Up and Down arrow keys on a focused handle, or use the right-margin move, duplicate, and remove buttons. An outline beside the editor jumps to any heading.

| Shortcut | Mac | Windows and Linux |
|---|---|---|
| Bold, italic, underline | ⌘B, ⌘I, ⌘U | Ctrl+B, Ctrl+I, Ctrl+U |
| Link | ⌘K | Ctrl+K |
| Heading, subheading, body text | ⌘⌥2, ⌘⌥3, ⌘⌥0 | Ctrl+Alt+2, Ctrl+Alt+3, Ctrl+Alt+0 |
| Bulleted and numbered list | ⌘⇧8, ⌘⇧7 | Ctrl+Shift+8, Ctrl+Shift+7 |
| Save now | ⌘S | Ctrl+S |

Text edits autosave to the draft, while password changes apply when you select **Save draft**. Select **Preview** to inspect the current draft in the reader layout.

Publishing a report creates an immutable numbered version that snapshots the text, introduction, captions, and artifact versions. Any artifact block set to follow latest pins the version current at publish time, so subsequent draft edits or new artifact uploads leave published report versions unchanged.

Reports start **private** and can be published as **unlisted** or **public** with an optional password. Public reports require public artifacts, unlisted artifacts require explicit sharing confirmation, and individual artifact passwords remain enforced inside reports. Updating a report password revokes active reader sessions, leaving the replacement field blank retains the existing password, and deleted artifact versions appear as unavailable in historical reports.

### Reports API

Scripts and AI agents manage reports through `/api/v1/projects/<project>/reports` using `FW_PROJECT_TOKEN` from `.env` or a personal API token.

| Action | Request |
|---|---|
| List reports | `GET /api/v1/projects/<project>/reports` |
| Read one | `GET /api/v1/projects/<project>/reports/<report>` |
| Create | `POST /api/v1/projects/<project>/reports` |
| Update draft | `PATCH /api/v1/projects/<project>/reports/<report>` |
| Publish | `POST /api/v1/projects/<project>/reports/<report>/publish` |

Report payloads pass an ordered `blocks` array. Text blocks accept Markdown or sanitized HTML (`"format": "html"`). Artifact blocks reference artifacts by slug:

```json title="Report payload"
{
  "title": "Penguin body mass",
  "publish": true,
  "blocks": [
    {"type": "text", "text": "## Body mass by species\n\nGentoo penguins have the highest mean body mass."},
    {"type": "artifact", "artifact": "mass-by-species"},
    {"type": "artifact", "artifact": "penguin-body-mass", "caption": "Linear model"}
  ]
}
```

Pass `markdown` rather than `blocks` for a single Markdown block and set `publish: true` to publish immediately. Responses return the report URL and version metadata. Built-in agent skill **Framework Cloud: publishing reports** documents these endpoints for coding agents.

## Storage

New artifacts reside on a private filesystem disk and stream through Framework Cloud application routes. Metadata, version histories, collections, reports, and password hashes reside in PostgreSQL. See [Server setup](server-setup.md#published-artifacts) for storage and backup configuration.
