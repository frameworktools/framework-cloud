# Code snippets

Status: approved 2026-11-18. Phase 1 shipped 2026-11-18: the Cloud kind, reader, and output toggle, and framework-embed 0.5.0. Phase 2 shipped 2026-11-18: the R and Stata clients, in framework 2.0.14 and Framework for Stata 0.3.5. Next: phase 3, Python notebooks.

A code snippet artifact carries a chunk's code and the printed text output a notebook reader saw under it. It is published from the notebook run, so a blog post that embeds it shows the code that produced the results beside it. The first user is the "bayesian-social-statistics" series on academic.page, which embeds artifacts through framework-embed and links each post to its full Quarto notebook.

The snippet stores printed text output only. `lm(y ~ x)` prints a few lines, and that is what the snippet keeps. Plots and other images are left out. Structured results stay with the `model` artifact, figures stay with figure artifacts, and a snippet can link to a model.

## Scope

| Phase | Work | Repository |
|---|---|---|
| 1 | `code_snippet` kind, validation, reader page, embed contract, framework-embed renderers | framework-cloud, framework-embed |
| 2 | R in Quarto and R Markdown; R scripts | framework |
| 2 | Stata do-files | framework-stata |
| 3 | Python notebooks: cell magic and executed `.ipynb` files | framework-py, framework-cli |
| 4 | Python scripts | framework-py |

Each phase ships on its own. Phase 1 is testable with hand-written JSON published through the API as the examples account.

Out of scope: images of any kind, rich HTML outputs (widgets, styled data frames), and running code on the server.

## Format

A snippet is a `framework.artifact/v1` JSON document with `kind: "code_snippet"`, published as a file like a model or table today. Every client sends the same structure.

```json
{
  "schema": "framework.artifact/v1",
  "kind": "code_snippet",
  "source": {
    "language": "r",
    "file": "notebooks/02-partial-pooling.qmd",
    "label": "fit-bayes",
    "lines": [41, 58],
    "commit": "3f2c9e1d…",
    "repository": "https://github.com/frameworktools/bayesian-social-statistics"
  },
  "snippet": {
    "width": 80,
    "blocks": [
      { "type": "code", "text": "fit <- brm(score ~ 1 + (1 | school), data = schools)" },
      { "type": "message", "text": "Compiling Stan program..." },
      { "type": "code", "text": "summary(fit)" },
      { "type": "output", "text": " Family: gaussian\n  Links: mu = identity ...", "folded": true },
      { "type": "warning", "text": "There were 3 divergent transitions after warmup." }
    ]
  },
  "display": {
    "folded": "auto",
    "links": [
      { "rel": "notebook", "url": "https://example.academic.page/notebooks/partial-pooling" },
      { "rel": "model", "artifact": "partial-pooling-model" }
    ]
  }
}
```

Fields:

- `source.language`: `r`, `python`, or `stata`. Required.
- `source.file`, `label`, `lines`, `commit`, `repository`: all optional. `label` is the chunk label, cell tag, or Stata marker. `lines` is the inclusive range in `file`. Cloud builds a GitHub permalink only from an `https://github.com/` repository with a 40-character commit, a relative `file`, and `lines`.
- `snippet.width`: console width at the run (R `width`, Stata `linesize`). Optional. Python sends none.
- `snippet.blocks`: ordered, as the notebook showed them. R and Stata interleave code and output per expression. A Python cell sends its code as one block, then its outputs.
- Block types: `code`, `output`, `message`, `warning`, `error`. Every block carries `text`. A Stata block sends `smcl` in place of `text`. Code blocks never fold.
- `folded` on an output, message, warning, or error block: `true`, `false`, or absent. Absent defers to `display.folded`.
- `display.folded`: `"auto"` (default), `true`, or `false`. Auto folds an output block longer than 12 lines.
- `display.links`: `notebook` takes an `https` URL. `model` takes an artifact slug in the same project, or an `https` URL.

The fold state sits on the block it applies to. A separate map keyed by block index would break when a client drops or merges blocks.

### Limits and normalization

Cloud enforces these on every snippet, whatever the client did:

- At most 200 blocks. A snippet document is at most 2 MB, below the 8 MB limit for other representations.
- Code blocks at most 400 lines in all. A snippet is a short excerpt. A longer chunk is rejected with a message that names the limit.
- Output, message, warning, and error blocks at most 500 lines and 100,000 characters each. Cloud keeps the first lines and records the rest as `truncated: {"lines": 1240}`. The reader shows "1240 more lines not shown." Counts group digits from five digits up, as framework-embed does.
- ANSI escape sequences become styled segments (see "Plain and styled text"). Line endings become `\n`. Trailing blank lines are trimmed.
- Unknown fields are dropped. Unknown block types, including images, reject the document, so a client bug fails at publish and does not show a broken embed.

Clients do not truncate. Cloud alone applies the limits.

### Plain and styled text

Every block in the stored snippet and the embed document carries both forms:

- `text`: plain text, with no markup or escape codes. Copy, search, `alt`, and renderers that ignore styling use it.
- `lines`: the same text as styled segments, one list per line, each segment a `[classes, text]` pair. `classes` is a space-separated string, empty for plain text.

```json
{
  "type": "output",
  "text": "       Model │  4821.0937\n─────────────┼───────────",
  "lines": [
    [["", "       Model │  "], ["res", "4821.0937"]],
    [["", "─────────────┼───────────"]]
  ]
}
```

Code and output share this shape, so renderers draw every block with one loop. Segments avoid character offsets, which count differently in PHP and JavaScript.

Cloud builds `lines` from what the client sent:

| Block | Client sends | Cloud builds `lines` from |
|---|---|---|
| Code | `text` | Phiki tokens (see "Highlighting") |
| R or Python output | `text`, ANSI codes kept | ANSI styles |
| Stata output | `smcl` | SMCL directives |

Output classes: `res` (Stata results), `err`, `inp` (Stata input), `bold`, `italic`, `underline`, `faint`, and the eight ANSI colors as `ansi-red`, `ansi-green`, and so on. Other ANSI codes are dropped. Bright colors map to the plain ones.

Clients send raw ANSI and raw SMCL so this conversion lives in one place, in PHP, with one test suite. The R, Python, and Stata packages only capture. The downloaded JSON is the client's original upload.

Under knitr, R output usually has no color, since cli and crayon turn color off during a render. It is styled only when the notebook showed color. Jupyter shows ANSI color in streams and tracebacks, so Python output keeps it.

## Cloud

### Publishing

`ArtifactRepresentation::parse` accepts `kind: "code_snippet"` and hands it to a new `ArtifactSnippet` class for validation and normalization. Tables and metrics stay required for the other kinds only. `code_snippet` joins the kind list in `ArtifactPublisher::rules()`, the MCP tool catalog (`app/Mcp/ToolCatalog.php:441`), and `ExternalArtifactsController`.

A snippet is one JSON file, so storage, download, and `ArtifactPublisher::merge()` need no changes.

### SMCL

`ArtifactSnippet` translates SMCL so the Stata layout and styles survive:

- Styles: `{txt}` is plain, `{res}` is `res`, `{err}` is `err`, `{inp}` and `{com}` are `inp`, `{hi}` and `{bf}` are `bold`, `{it}` is `italic`, `{ul on}` and `{ul off}` toggle `underline`. A directive with a colon, such as `{res:12.34}` or `{bf:Note}`, styles only its argument.
- Layout: `{hline N}` and `{hline}` become `─`. `{c |}`, `{c +}`, `{c TT}`, `{c BT}`, and the other `{c …}` names become box-drawing characters. `{col N}`, `{space N}`, `{ralign N:…}`, `{lalign N:…}`, and `{center N:…}` become spaces, measured against `width`.
- Links: `{help …}`, `{browse …}`, and `{stata …}` keep their text and drop the link.
- `{.-}`, `{p}`, and other paragraph directives reduce to their text. Unknown directives keep their text, so nothing printed is lost.

In the reader, `res` is bold and dark, `inp` bold, and `err` red, as in Stata's standard results window.

### Highlighting

Cloud tokenizes code with Phiki, which already colors the docs (`app/Support/Docs/CodeBlocks.php`) and ships TextMate grammars for R, Python, and Stata (`vendor/phiki/phiki/resources/grammars/stata.json`). Tokens are built once in `ArtifactPreview` and cached with the preview.

TextMate scopes map to eight classes: `comment`, `string`, `number`, `keyword`, `function`, `variable` (Stata macros, R arguments), `operator`, and `constant`. Everything else is plain text.

The Stata grammar marks any word that names a command as a keyword, wherever it appears. In `regress score income`, `score` is colored as a keyword, since `score` is also a command (checked with Phiki 2.2.1). The mapping corrects this: in Stata, a `keyword.control.flow` token counts as a keyword only in command position, at the start of a line or after a prefix such as `quietly`, `capture`, or `by …:`. R and Python tokens map as the grammars give them.

Code segments use the classes `kw`, `str`, `num`, `fn`, `var`, `op`, `const`, and `com`, in the shared `lines` shape.

The reader and both embed renderers draw every segment as `<span class="fw-kw">`, styled for light and dark. Highlighting in the browser would need highlight.js in the JS bundle and a separate highlighter in the PHP core, and the two would color code differently. Pre-built tokens keep the renderers data-only and identical, as the embed plan requires. `text` remains for the copy button and for renderers that ignore tokens.

### Reader page

`ArtifactPreview` returns `type: "code_snippet"`, and `documents/show.blade.php` renders a new `x-artifact-snippet` component. Load the `cloud-design` skill first. The layout:

- Header: file name with the label (`02-partial-pooling.qmd · fit-bayes`), language, and the line range. The range links to the GitHub permalink when Cloud can build one.
- Code blocks: highlighted, monospace, with a Copy code button. Copy takes all code blocks joined, without output.
- Output blocks: plain monospace on a lighter ground, unwrapped, with horizontal scroll. `width` sets the minimum width in `ch`, so tables printed at 80 columns stay aligned. Folded blocks use `<details>` with a summary such as "Output, 34 lines."
- Messages: muted. Warnings: amber with a left rule. Errors: red with a left rule. No extra label, since R and Python already print "Warning:" and "Error" in the text.
- Footer links: Full notebook, and Model when the linked artifact resolves.

A `model` link by slug resolves to the artifact's page only when a reader of the snippet may open it: the same project, not deleted, and at least as open as the snippet. Otherwise the link is left out, so a public snippet never names a private model.

Versions, visibility, passwords, and the version menu work as for other artifacts. Reports embed snippets through the existing reader frame. `resources/js/report-artifacts.js` gets a "Code snippet" label and icon.

### Embed contract

`framework.embed/v1` gains `kind: "code_snippet"`. `data` carries the normalized snippet with tokens. It needs no new routes. Model links appear only when the model is embeddable. Renderers older than this release treat `code_snippet` as `page` and show the link card. `EmbedAlt` describes a snippet as "R code from 02-partial-pooling.qmd, with its output."

### Code without output

A snippet can appear without its output in two ways.

- At publish. Every client takes an output switch: `output = FALSE` in R, `fw_snippet end, nooutput` in Stata, `--no-output` on the Python magic and `fw snippet publish`, and `output=False` in `fw.snippet()`. The client then sends code blocks only, which the format already allows.
- Per link. `?output=none` on a snippet's link (`/d/{key}`, the readable link, or an embed route) hides the output at first. The embed document carries the output with `display.output: false`. The reader page and the framework-embed renderers show a Show output / Hide output toggle beside Copy code, whenever a snippet has output. On the reader page the toggle also adds or removes `?output=none` in the address, so a copied link opens the same way. The embed document's `url` and `embed_url` keep the option, so a site that fetches again keeps it too. Other kinds ignore it. framework-embed's URL matcher keeps `output=none` and drops any other query. Server-rendered embeds without the script show no toggle, so hidden output stays hidden.

### framework-embed

Release 0.5.0, shipped as recorded for earlier releases:

- `spec/embed.schema.json`: the `code_snippet` data definition. `SPEC.md`: the section and its rendering rules.
- Fixtures: an R snippet with a folded summary and a warning, a Stata snippet with a styled regression table, a Python snippet with an error, and a truncated output.
- JS and PHP renderers with identical markup. Folding uses `<details>`, so the PHP output works without script. The JS bundle adds the copy button. Without the bundle, code stays selectable.
- CSS for code tokens, output classes (`res`, `err`, `inp`, text styles, ANSI colors), messages, warnings, and errors, in light and dark.
- Kind dispatch changes in three places: `KINDS` in `js/src/render.js:10`, the PHP list in `php/src/Renderer.php:22`, and the representation kind enum in `spec/embed.schema.json:67`. New fixtures join the required list in `js/test/fixtures.test.js:15`.
- The URL matcher (`js/src/url.js`, `php/src/UrlMatcher.php`) keeps `?output=none` on artifact URLs and on the URL it requests.
- The output toggle: `data-fw-output`, shown by `enhanceOutput()` in the bundle. `display.output: false` renders output blocks `hidden`. Fixture `snippet-output-hidden`.
- Cloud vendors the new copy in `public/vendor/framework-embed`.

## R

Phase 2.

All clients publish today through `POST /api/v1/documents` with `file`, `slug`, `title`, `description`, `kind`, and `visibility`. None captures chunk output yet: there are no knitr hooks, IPython magics, or Stata log capture. A snippet uploads as `code_snippet.json` through the same request.

In R, `publish_snippet()` builds the JSON beside the other representations in `R/artifact_objects.R` and uploads through `.fw_cloud_publish()` (`R/cloud.R:1192`). The kind from the file name (`R/publish.R:486`) learns `code_snippet.json`. knitr is already in Suggests. `evaluate` joins it.

### Quarto and R Markdown

```r
#| label: fit-bayes
fit <- brm(score ~ 1 + (1 | school), data = schools)
summary(fit)
```

```r
#| include: false
publish_snippet("fit-bayes", folded = TRUE, links = list(notebook = "https://…"))
```

`library(framework)` under knitr installs wrappers around knitr's output hooks (`source`, `output`, `message`, `warning`, `error`). The `plot` hook is left alone, so plots stay in the rendered notebook and out of the snippet. Each wrapper records its piece for the current chunk label and passes the piece through unchanged. The record therefore holds what knitr rendered: `results = "hide"`, `echo = FALSE`, `message = FALSE`, and `warning = FALSE` already applied. `publish_snippet(label)` publishes that chunk's record from any later chunk. Hooks attach when the package loads, so the labelled chunk must come after `library(framework)`.

knitr adds the comment prefix (`options$comment`, `## ` by default) before it calls the output hook (`knitr:::sew.character`). The wrapper strips that prefix, so the snippet does not carry `## ` on every line. `results = "asis"` output never reaches the output hook in HTML output, so it is not recorded. Quarto installs its own hooks before the first chunk, and the wrappers wrap those. This needs a test under both `quarto render` and `rmarkdown::render()`. `width` is `getOption("width")` at the chunk.

Outside a render, as when a chunk runs in the editor, no hooks fire. `publish_snippet()` then publishes nothing and says why. It never re-runs the chunk.

### R scripts

```r
fw_snippet("fit-bayes", {
  fit <- brm(score ~ 1 + (1 | school), data = schools)
  summary(fit)
})
```

`fw_snippet()` captures the braced expression unevaluated, runs it through `evaluate::evaluate()` with the source text from its srcref, prints the output as normal, assigns into the calling environment, and publishes the code and text outputs. Graphics that `evaluate` returns are dropped. `evaluate` is already a knitr dependency. Source lines come from the srcref. A script run with `Rscript` keeps srcrefs only when `options(keep.source = TRUE)`. Without them, the code is deparsed and the snippet says so in `source`.

## Stata do-files

Phase 2.

```stata
fw_snippet begin fit-ols
regress score income i.region
fw_snippet end, folded
```

The command follows the package's `fw_` naming (`fw_publish`, `fw_table`). `fw_snippet begin` opens a named SMCL log (`log using …, name(fw_snippet) smcl`) beside the user's own log. `end` closes it, splits it, and publishes. Stata already echoes each command above its output, so the client splits the log at command lines (`{com}. `), with continuation lines (`> `) kept with their command. Command text becomes a code block's `text`, with the `. ` prompt removed. The SMCL between commands becomes an output block's `smcl`, unchanged. Cloud translates it (see "SMCL"). The `fw_snippet end` line is dropped. The splitting is Mata in a new `fw_snippet.mata`, and stays a line-level job. Publishing reuses `fw_publish`'s backends (core curl, the `fw` CLI, or the Python bridge), so it needs no Python.

Cloud makes an output block an `error` block when its first printed text is `{err}`, so the client does not judge it. `width` is `c(linesize)`. Stata exposes no running do-file (`c(filename)` is the last dataset), so `fw_snippet end` searches the project's do-files for `fw_snippet begin <label>` and records the file and the lines between the markers. When several do-files match, the file is left out; `file()` names it.

A snippet nested inside another, or a `begin` without an `end`, fails with a message. A do-file that stops with an error between `begin` and `end` leaves the log open. The next `fw_snippet begin` closes it first.

Graphs never enter a snippet. A `graph` command's echo stays in the code, and its window is not recorded.

Stata run in Jupyter through nbstata or pystata takes the Python path.

## Python notebooks

Phase 3.

```python
%load_ext framework
```

```python
%%fw_snippet fit-ols --folded
model = smf.ols("score ~ income", data=df).fit()
print(model.summary())
```

The cell magic runs the cell in the user namespace, shows its outputs as normal, and publishes the cell's code and outputs: stdout, stderr (as `message`), the `text/plain` form of results and displays, and the exception if one is raised. An output that carries an image type (a matplotlib figure, for example) is skipped whole, so its `<Figure size 640x480 with 1 Axes>` text does not appear either. A data frame shown as an HTML table is recorded as its `text/plain` form, which is what `print()` shows. The exception is re-raised after publishing. `%%fw_snippet` is dropped from the published code. This works in Jupyter, VS Code, and Quarto's Jupyter engine. framework-py has no IPython code today. `load_ipython_extension` imports IPython lazily, so the package does not depend on it.

Executed notebooks publish without running:

```sh
fw snippet publish analysis.ipynb --cell fit-ols
```

This is a new `fw snippet` command group in the Go CLI (`internal/cli/snippet.go`, registered in `internal/cli/root.go`), with `code_snippet` added to `artifactKinds` (`internal/cli/publish.go:27`). The cell is found by tag or by a Quarto `#| label:` line. Outputs come from the saved `outputs`: `stream`, `execute_result` and `display_data` as `text/plain`, with the same rule for outputs that carry an image, and `error` with its traceback. Quarto option lines (`#| …`) are dropped from the code.

## Python scripts

Phase 4.

```python
with fw.snippet("fit-ols"):
    model = smf.ols("score ~ income", data=df).fit()
    print(model.summary())
```

The block's source comes from the calling frame's file and line range. Output is stdout and stderr, teed so it still prints. A script shows no expression values, and none are recorded.

## Testing

- Cloud: Pest tests for validation, limits, truncation, ANSI and SMCL translation against logs from real Stata runs, rejection of image blocks, token output for each language, permalink rules, model link visibility, and the embed document.
- framework-embed: fixtures rendered by both renderers with identical output, as today.
- Clients: unit tests with recorded outputs. An end-to-end check per client publishes to framework.pub as the examples account through `scripts/as-examples`, in a `frameworktools` example repository.

## Package repositories

The parity work has shipped: framework 2.0.8, framework-stata 0.3.0, and framework-py 0.1.0.dev22. Its worktrees were clean on 2026-11-18. Snippet work in each package starts on a new branch from its default branch. If a parity worktree has new changes when the work starts, it stays untouched.

Other publish drivers (GitHub Pages and the static reader in `fw` and framework-stata) show a snippet as a downloadable file at first. A static rendering can follow once the Cloud reader settles.

## Versions

Code snippets keep versions, as every artifact does. A snippet with no versions would need exceptions in the reader, the version menu, the API, and the embed routes.

Versions also serve the blog. An embed can name a version (`/versions/{n}`), and academic.page stores the embed JSON it fetched. A post that pins version 3 of a model and version 3 of its snippet keeps code and results together after the notebook moves on. A post that wants the newest code embeds the artifact without a version.

Every render of the notebook publishes again. When the normalized snippet matches the newest version, Cloud returns that version with `unchanged: true` and adds none. The version list then grows only when code or output changes. This is new behavior for one kind. It could extend to models and tables later.

## Decisions

Approved 2026-11-18:

1. Cloud builds highlight tokens and styled segments. Renderers never highlight or parse SMCL or ANSI.
2. Snippets keep versions. Unchanged snippets add no version. The API answers `200` with `unchanged: true`.
3. Clients send `repository` only when the user passes it or sets `publish.repository` in `settings.yml`. Commit and file go by default.
4. Stata output keeps its SMCL styles.

## Implementation notes

Cloud, phase 1:

- `app/Support/Snippets/`: `ArtifactSnippet` (validation, limits, links, alt text), `StyledText` (the shared lines shape and its HTML), `AnsiText`, `SmclText`, and `CodeHighlighter` (Phiki).
- `ArtifactPublisher` validates a snippet strictly, rejects a broken one with a message on `file`, and skips the version for an unchanged one. `DocumentVersion::$unchanged` carries that to the API answers.
- The reader is `resources/views/components/artifact-snippet.blade.php`, with token and output styles in `resources/css/app.css`.
- `?output=none`: read by `ArtifactEmbed::resolve()`, `EmbedController`, and the reader page. Embeds get `display.output: false`; the reader starts with its output blocks `hidden`.
- Tests: `tests/Feature/CodeSnippetTest.php`.
