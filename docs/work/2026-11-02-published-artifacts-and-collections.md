# Published artifacts and collections

The project now treats a published output as a versioned artifact. The existing
Document identity and routes remain compatible with earlier publications.
Artifacts include rendered HTML and books, images, tables, text, PDFs, and
files for download. Collections organize existing artifacts within a project.

## Product decisions

- Every artifact has a reader page with a preview, metadata, version navigation,
  and a download link.
- Metadata, access rules, version history, and collection membership live in
  the database. File bytes remain on a private filesystem disk and stream
  through application routes. This supports passwords without placing large
  files in database rows.
- New content uses `FW_ARTIFACTS_DISK` (default `local`). It must be private and
  persistent. Multiple app nodes need shared private storage. Existing external
  document storage remains compatible.
- Unlisted links are described as unlisted. Artifact passwords gate pages,
  original files, supporting assets, downloads, and earlier versions.
- Collections can be private, unlisted, or public and can have their own
  password. Collection access does not override artifact access. Public
  collections require public artifacts; unlisted sharing requires consent.
- Entries follow the latest version or pin a numbered version. A deleted pinned
  version removes its entry from the reader listing rather than advancing it.
- Merging appends copies of a source artifact's versions to a destination.
  Source links remain available. Sources become read-only for publishing,
  including after the destination is deleted. Provenance records source
  identity and version. File contents are not combined.
- Version numbers are never reused after deletion. Publish, merge, settings,
  and delete operations serialize on the project row.
- Hosted artifacts use the existing paid publishing entitlement. No price or
  billing integration changed.

## Clients

R, Python, and the CLI accept generic files, publication metadata, and collection
assignment. R and Python support explicit Cloud and bucket destinations while
retaining existing bucket-first defaults. Local adapters cover ggplot, data
frames, lm/glm, matplotlib, pandas, and statsmodels summaries. Models do not
include serialized objects or observation-level augmentation automatically.
A failed collection attachment reports the successful publication and its URL.

## Reader isolation

Uploaded HTML has an opaque sandbox origin, including when opened directly.
Rendered bundles use expiring version-scoped content capabilities so modules
and search indexes can load without application cookies. Capabilities expire
and are revoked by password changes. Stable reader links apply password checks
before issuing content access.

## Review fixes

Independent review identified version selection being lost during password
unlock, incorrect paths for nested book entrypoints, races between publication
and version deletion, sandbox restrictions on resource loading, omitted empty
CLI metadata updates, and colliding CLI book names. These were addressed.
Owner preview of a private collection now includes its unlisted entries.

## Boundaries

This iteration does not implement reviewer accounts, comments, DOI registration,
collection release snapshots, general model execution, or archival retention
guarantees. Collections support exact per-entry version pins. Unsupported object
classes can be saved locally and published as downloadable files. Direct seaborn
grid objects require extracting their underlying matplotlib Figure first.

The initial operational upload limit is 50 MiB, with rendered bundles limited
to 200 MiB expanded and 4,000 entries. These are upload bounds, not paid-plan
storage allowances. Deployment must run the two migrations and provision and
back up private artifact storage.

## Verification

Cloud's focused integration and regression checks passed: 79 tests with 636
assertions. Python's focused suite passed 66 tests, Ruff, and mypy. The targeted
R and Go publishing suites passed; external S3 integration was skipped. An
additional real statsmodels OLS fit successfully exported its HTML summary.
The frontend production build and PHP formatting checks passed.

Both new migrations were applied to the local development database after
confirming APP_ENV=local. No commits, pushes, or production deployments were
performed. Client changes remain in the adjacent framework, framework-py,
and framework-cli working trees. Browser interaction has not been verified.
