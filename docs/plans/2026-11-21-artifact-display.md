# Artifact display settings

Status: approved 2026-11-21.

Framework Cloud owns what an embedded artifact shows. Each artifact has display settings, such as whether a DAG shows its legend, analysis, DAGitty code, or R code. The owner sets them on the artifact's Settings tab, and the packages can set them when they publish. Embed documents carry only what the settings allow, so every consumer (framework-embed, academic.page, WordPress, Drupal, Statamic) follows them without a setting of its own.

## Settings

Settings are on by default, except that a DAG embeds as its drawing alone: its `description`, `analysis`, `dagitty`, and `code` start off. An artifact stores the settings set for it, in `documents.display` (JSON, keys to booleans), and they apply to every version.

| Key | Kinds | Label | Off means |
|---|---|---|---|
| `title` | all but page | Title | No title above the artifact. |
| `description` | all | Description | No description. |
| `author` | all | Author | No author name. |
| `credit` | all | Link to {server} | No link back to the artifact. |
| `details` | model, test | Model details, Test details | No details section. |
| `variable_tips` | model | Variable tips | No tips on terms. `variables` and `terms` are left out. |
| `column_summaries` | table | Column summaries | Column summaries are left out of tips. |
| `output` | code_snippet | Output | The output starts hidden, with Show output. |
| `links` | code_snippet | Notebook and model links | No links under the code. |
| `legend` | dag | Legend | No role legend under the drawing. |
| `analysis` | dag | Analysis | No adjustment sets or implications. |
| `dagitty` | dag | DAGitty specification | No DAGitty code. |
| `code` | dag | {Language} code | No source code. Offered only when the DAG has code. |
| `interactive` | vega-lite | Interactive chart | The static image shows instead. Offered only when the chart has one. |

`?output=none` on a snippet link still hides its output for that link.

## Contract

Cloud leaves out what a setting turns off: `title`, `description`, and `author` become `null`, and the data the section would draw (`analysis`, `dagitty`, `code`, `variables` and `terms`, column summaries, snippet links) is dropped. Turning `interactive` off sends the figure as `image`. Three settings need the renderer: `data.display.details: false` (model and test) and `data.display.legend: false` (dag), whose content also feeds what stays, and `display.credit: false` at the top of the document, since renderers otherwise link the artifact's URL. framework-embed 0.8.0 reads the first two and 0.8.2 the third; older renderers show the part.

The reader page shows everything. The settings shape what other sites receive.

Stored embeds keep the document they fetched until the author fetches it again. Newest-version documents are cached for 5 minutes and version documents for an hour.

## Publish answer

Both publish APIs answer with `embed_link`: the link to paste into a site that takes Framework embeds. That is a public artifact's short link, or an unlisted artifact's own link. It is `null` when the artifact can't be embedded (private, or with a password). They also answer with `artifact_url`, the artifact's page on Framework Cloud, which its owner opens to share it, set what embeds show, and edit it. The packages print that page after they publish.

## Setting them

- Cloud: the artifact's Settings tab, section **Embed contents**, lists the settings that apply to its kind and content.
- API: `display` on publish (`POST /api/v1/documents`, `POST /api/v1/artifacts`, uploads), a JSON object of keys to booleans. Keys it names change; others stay. Unknown keys and non-boolean values are refused with the list of keys.
- R: `publish(..., display = list(analysis = FALSE))`. Python: `fw.publish(..., display={"analysis": False})`. Stata: `display(analysis=off dagitty=off)` on `fw_publish` and `fw_dag`. fw: `--display analysis=off` (repeatable).
- MCP: `display` on `artifacts_publish`.
