# Publish drivers

Status: implemented locally, 2026-11-04. Changes span framework-cloud,
framework (R), framework-py, and framework-cli. No releases or deployments.

## Pipeline

Each client builds a `framework.bundle/v1` bundle before delivery. Bundles
contain `artifact.json`, `index.html`, the original file, optional assets,
and `representation.json` for typed artifacts. Manifests record source metadata,
file sizes, and SHA-256 checksums. Slugs use lowercase letters, numbers, and
hyphens.

Drivers support Framework Cloud, GitHub Pages, folders, and S3-compatible
storage. Explicit destinations override project defaults. Existing bucket and
Cloud routing remains compatible. Legacy bucket uploads retain their raw layout.

## Reader

Blade compiles the portable reader at build time:

```sh
php artisan artifacts:build-reader
```

The source template is `resources/views/artifacts/static.blade.php`. JavaScript
and CSS are embedded in `resources/static-reader/reader.html`. Packages ship
this template. Bundles work offline without PHP or a CDN.

The portable reader supports searchable and sortable data tables, coefficient
tables, forest plots, model summaries, statistical-test summaries, figures,
and document frames. Numeric displays default to three decimal places. Hover
text retains exact values. Coefficient tables preserve model order.

Cloud retains its richer Blade displays for specialized model outputs and
column profiles. Both readers share the table code. Full visual parity remains
follow-up work. Raw Vega chart specifications currently download from portable
bundles; Cloud retains its interactive chart renderer.

## Settings

```yaml
publish:
  default: lab
  destinations:
    lab:
      driver: folder
      path: /Volumes/lab/published
      visibility: private
    pages:
      driver: github
      visibility: public
```

Saved split settings place this under `default.publish`. Clients also accept
the root form. Named destinations require environment references for credentials.
`visibility` is canonical. `private: true` remains a compatibility alias.
Explicit visibility overrides destination settings.

Account defaults seed Cloud or GitHub destinations in new projects. Folder
and bucket details remain project settings. R's canonical creation function is
`new_project()`, including Cloud key setup. `new()` remains a compatibility alias.

## Delivery and access

Folder, GitHub, and named S3 destinations retain version archives under
`<slug>/v/<number>/`. Site indexes use `index.html` and `index.json`.
GitHub site size includes retained versions. Drivers warn around 800 MB and
reject sizes above 1 GB before push.

Cloud supports public, unlisted, and owner-only private artifacts. Passwords
remain available on Cloud. External destinations reject unsupported password,
unlisted, and expiry requests before delivery.

Private GitHub Pages requires API evidence of restricted site access in R and
Python. The CLI rejects private GitHub publishing. A private repository alone
does not establish private Pages. Private S3 delivery requires successful
bucket privacy checks. Unsupported checks fail closed. Folder permissions remain
the filesystem or server administrator's responsibility.

## Cloud tracking

`POST /api/v2/projects/{slug}/artifacts/external` records destination metadata,
checksums, sizes, and versions. It stores no files or local paths. Version
replays are idempotent. Conflicting receipts fail with HTTP 409.

External artifacts show a Location column. Cloud previews, passwords, sharing,
view counts, merging, collections, and report embedding remain Cloud-only.
Removing an external tracking record leaves destination files intact.

Legacy raw bucket receipts use version 1. Replacement uploads may produce a
tracking conflict warning after successful delivery.

## Storage

Hosted accounts include free publishing and a 1 GiB content budget across
artifact versions. Self-hosted installations have no default cap. Override
with `FW_PUBLISH_STORAGE_LIMIT_BYTES`; zero disables the cap.

The budget counts published content bytes. Retained upload archives and sidecar
storage overhead are additional physical storage. Report text remains in the
database. External content does not consume this budget.

## Validation and limits

Real R dataframe, lm, and ggplot artifacts passed offline browser checks.
Python pandas, fitted statsmodels, and matplotlib artifacts passed. CLI-generated
table and figure bundles passed. Cloud management pages passed light, dark,
and 390px checks. A real external metadata request and its management page passed.

Live AWS S3 delivery passed in R, Python, and the CLI. Public reader URLs
returned HTTP 200. Private readers returned HTTP 403 anonymously and matched
authenticated readback. Version archives were verified. Temporary buckets and
objects were removed.

The live GitHub Pages API rejection path passed against a private repository
without restricted Pages. Successful restricted Pages delivery still needs an
Enterprise Cloud test repository. Independent S3 publishers do not have cross-client write locking.
Multi-file S3 delivery is not atomic. Collections remain Cloud-only.

Schema copies currently record local R worktree provenance. Release preparation
must commit the canonical schemas, resync all clients from that commit, and
rerun provenance checks. The CLI's committed-source provenance gate currently
fails while those schema changes remain uncommitted.
