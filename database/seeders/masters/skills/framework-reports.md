---
name: framework-reports
description: Use when asked to write up results as a report or notebook page on Framework Cloud - an ordered page of Markdown or HTML text and published figures, tables, and models, built and published through the Framework Cloud reports API from R, Python, Stata, or a terminal.
metadata:
  title: Creating Framework reports
  summary: Build a report of text and published results through the Framework Cloud API, then publish it at a stable link.
---

# Creating Framework reports

A report is one page that reads like a notebook: text blocks in Markdown (or
HTML) with published results placed between them. Each result is an artifact
already published from the project, such as a figure, table, or model, shown
at a pinned version. Publishing a report creates a version at a stable link;
publishing again adds the next version at the same link.

## Before you start

- Publish the results first. The report embeds published artifacts by their
  slug, so each figure, table, or model needs `publish()` (R),
  `fw.publish()` (Python), `fw_publish` (Stata), or `fw publish` (terminal)
  before it can appear in a report.
- The project's `.env` has `FW_CLOUD_URL` and `FW_PROJECT_TOKEN`. Use them for
  the API. Never print the token, write it into a file, or put it in the
  report. If the project has no token, ask the user to connect it to
  Framework Cloud; don't create one yourself.
- Ask before choosing `public`. Reports start `private`. A public report may
  contain only public artifacts, and an unlisted report needs
  `"share_unlisted": true` on each unlisted artifact it shows.

## The API

All requests go to `$FW_CLOUD_URL/api/v1/projects/<project-slug>/reports`
with `Authorization: Bearer $FW_PROJECT_TOKEN` and JSON bodies.

| Action | Request |
|---|---|
| List reports | `GET  .../reports` |
| Read one | `GET  .../reports/<report-slug>` |
| Create | `POST .../reports` |
| Update the draft | `PATCH .../reports/<report-slug>` |
| Publish the draft | `POST .../reports/<report-slug>/publish` |

Create and update take:

- `title` (required to create), `description`, `slug`, `visibility`
  (`private`, `unlisted`, or `public`), `password` (at least 8 characters).
- `blocks`: the page, in order, up to 100 blocks.
- `markdown`: instead of `blocks`, one Markdown text block. Send one or the
  other.
- `publish: true`: publish right after saving.

Blocks:

```json
{"type": "text", "text": "## Methods\n\nWe fit a linear model..."}
{"type": "text", "format": "html", "text": "<p>Written as <em>HTML</em>.</p>"}
{"type": "artifact", "artifact": "flipper-and-mass", "caption": "Flipper length and body mass"}
{"type": "artifact", "artifact": "penguin-body-mass", "version_number": 2}
```

- Markdown is the default for text. Raw HTML inside Markdown is removed. With
  `"format": "html"`, a small set of tags is kept (paragraphs, headings h2-h3,
  lists, links, emphasis, code, quotes) and everything else, including
  scripts, iframes, and images, is dropped. Show figures as artifact blocks,
  not `<img>` tags.
- `artifact` is the published artifact's slug in this project. Without
  `version_number`, publishing pins the latest version.
- `id` is optional. Give blocks stable ids if you will update them later.

The response includes `url` (the stable link), `latest_version`, each
version's own `url`, and the draft `blocks`.

## Examples

Terminal:

```sh
curl -sS -X POST "$FW_CLOUD_URL/api/v1/projects/penguin-study/reports" \
  -H "Authorization: Bearer $FW_PROJECT_TOKEN" -H "Content-Type: application/json" \
  -d '{"title": "Penguin body mass", "slug": "penguin-body-mass-report", "publish": true,
       "blocks": [
         {"type": "text", "text": "## Body mass by species\n\nGentoo penguins are heaviest."},
         {"type": "artifact", "artifact": "mass-by-species"},
         {"type": "artifact", "artifact": "penguin-body-mass", "caption": "Linear model of body mass"}
       ]}'
```

R (httr2):

```r
library(httr2)
report <- request(Sys.getenv("FW_CLOUD_URL")) |>
  req_url_path_append("api/v1/projects/penguin-study/reports") |>
  req_auth_bearer_token(Sys.getenv("FW_PROJECT_TOKEN")) |>
  req_body_json(list(
    title = "Penguin body mass",
    blocks = list(
      list(type = "text", text = "## Body mass by species"),
      list(type = "artifact", artifact = "mass-by-species")
    )
  )) |>
  req_perform() |>
  resp_body_json()
report$url
```

Python:

```python
import os, requests
response = requests.post(
    f"{os.environ['FW_CLOUD_URL']}/api/v1/projects/penguin-study/reports",
    headers={"Authorization": f"Bearer {os.environ['FW_PROJECT_TOKEN']}"},
    json={"title": "Penguin body mass", "markdown": "## Draft\n\nNotes so far."},
)
response.raise_for_status()
print(response.json()["url"])
```

In R and Python, the `.env` values are loaded by `scaffold()`. In a terminal,
load them first, for example `set -a; . ./.env; set +a`.

## Updating a report

To revise, `PATCH` the report with the full `blocks` list you want (it
replaces the draft), then publish. The link stays the same and readers get the
new version; earlier versions stay available at their own links. Re-publish
the underlying artifacts first if their content changed, so the report pins
the new versions.

## Rules

- Write the text from the analysis that was actually run. Don't invent
  numbers; take estimates and counts from the published results or the
  project's outputs.
- Never include identifiable or private data in report text, and never embed
  artifacts published from private folders. See the
  `framework-sensitive-data` skill if the project has one.
- Tell the user the report's `url` and its visibility when you finish.
