# Object adapters and report builder

This extends the published-artifact work with typed object representations,
interactive readers, and a report builder for users who do not use Quarto.

## Presentation reference

The user directed us to phpstats.org. Its `example-result`, `df-table`, and
`fit-table` Blade components supplied the presentation pattern: dispatch by
object type, show typed column headers and table dimensions, and present model
coefficients with fit statistics. Framework Cloud adapts that pattern with
search, sorting, pagination, preview CSV export, and coefficient plots. It does
not depend on the sibling phpstats repositories.

## Representation contract

R and Python emit standalone `framework.artifact/v1` JSON for Cloud tables and
model summaries. It records kind, source language/classes, ordered typed
columns, scalar row values, full row counts, model metrics, and optional
formula. Representations are versioned through the existing artifact service.
Bucket exports retain CSV and HTML behavior.

Cloud validates structure, dimensions, types, dates, and finite numbers before
rendering. It caps previews to 8 MiB of representation data, 10 tables, 50
columns, 1,000 rows per table, and 50 metrics. The full uploaded file remains
available to download. Notices explain truncation. Large integers become text
before browser JSON parsing so identifiers do not change. No R/Python model
objects are deserialized on Cloud.

R supports data frames/tibbles, ggplot, lm/glm, optional formatted-table
packages, and selected broom models. Python supports pandas,
matplotlib Figure/Axes, seaborn grids, and statsmodels coefficient results.
Models export summaries without observation-level data or serialized model
internals. R selects t or normal Wald intervals from the summary statistic;
Python follows statsmodels. Interval method and confidence level are stored.

## Report builder

Project Reports contains an editor for text and artifact sections. Authors
can arrange sections, choose artifact versions, write captions, preview a
saved draft, and publish an immutable report version. The reader has a cover,
section navigation, sanitized Markdown, and compact embedded artifact readers.

Publication pins current artifact versions and snapshots labels/kinds as well
as report text and metadata. Later draft edits and artifact renaming do not
change old report versions. Deleted referenced outputs show as unavailable.
Private/unlisted/public sharing follows the project's ownership boundary;
unlisted artifact sharing requires explicit consent. Report passwords gate
the report without granting access to separately protected artifacts.

The new reports migration was applied to the local development database.
Nothing has been committed, pushed, or deployed.

## Verification

Cloud integration and regression checks passed: 92 tests with 707 assertions.
The final reader, report, and guide subset passed another 23 tests with 184
assertions after the last presentation changes. Six Node tests passed for table
behavior and CSV encoding. Real Chrome checks cover numeric/stable/null sorting,
search, pagination, keyboard controls, ARIA states, CSV export, and script/HTML
escaping. Python passed 73 focused tests, Ruff, and mypy. Targeted R publishing
and interval tests passed. Cloud's actual parser accepted all 12 generated R
and Python fixtures, including OLS/GLM models, dates, nulls, zero-row tables,
and large identifiers. Optional R formatted-table packages were absent, so
their integration checks were skipped. The production frontend build, PHP
formatting, and landing-page render checks passed.

A final real Chrome editor test also passed for plain-text defaults, optional
formatting with caret/focus preservation, adding/reordering/removing sections,
version pinning, sharing consent, and password-safe save payloads. It loads the
built application against intercepted fixture requests without a server.
