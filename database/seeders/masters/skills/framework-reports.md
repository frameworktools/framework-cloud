---
name: framework-reports
description: Use when asked to write up results as a report or notebook page on Framework Cloud - an ordered page of Markdown or HTML text and published figures, tables, and models, built and published through the Framework Cloud reports API from R, Python, Stata, or a terminal.
metadata:
  title: "Framework Cloud: publishing reports"
  summary: Build a report of text and published results through the Framework Cloud API, then publish it at a stable link.
---

# Framework Cloud: publishing reports

A report is one page that reads like a notebook: text blocks in Markdown (or
HTML) with published results placed between them. Each result is an artifact
already published from the project, such as a figure, table, or model, shown
at a pinned version. Each publish adds a version at the same stable link.

## Requirements

- Publish the results first. The report embeds published artifacts by their
  slug, so each figure, table, or model needs `publish()` (R),
  `fw.publish()` (Python), `fw_publish` (Stata), or `fw publish` (terminal)
  before it can appear in a report.
- The project's `.env` has `FW_CLOUD_URL` and `FW_PROJECT_TOKEN`. Use them for
  the API. Keep the token out of output, files, and the report. If the project has no token, ask the user to connect it to
  Framework Cloud.
- Ask before choosing `public`. Reports start `private`. A public report may
  contain only public artifacts, and an unlisted report needs
  `"share_unlisted": true` on each unlisted artifact it shows.

## API

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

## Example

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

In a terminal, load the `.env` values first, for example
`set -a; . ./.env; set +a`. In R and Python, `scaffold()` loads them.

## Report updates

To revise, `PATCH` the report with the full `blocks` list, which replaces the
draft, then publish. The link stays the same and shows the new version.
Earlier versions keep their own links. Republish changed artifacts first, so
the report pins their new versions.

## Text

- Take estimates and counts from the published results or the project's
  outputs.
- Tell the user the report's `url` and its visibility when you finish.
