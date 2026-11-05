# Landing workflow and ignore rules

The landing walkthrough now has three steps: Start your project, Conduct your
analysis, and Publish your work. The first combines project creation with
clickable settings, package, connection, notebook, and skill examples. Package
files retain their install example. Console entries have terminal icons.
Language guide links now read R and Python. Machine creation precedes ZIP and
GitHub in the project-structure section.

Security and Cloud cards place icons above headings. The security section names
the Data verification ledger and explains hash tracking, change warnings, and
sharing metadata without datasets.

Each system structure now owns editable git.gitignore_content in its layer.
GitignoreTemplate supplies language-neutral structure rules; package defaults
still come first during client resolution. Course rules include grades,
submissions, and student data. Sensitive structures name private input,
reference, and output folders. The structure catalog previews these rules and
the editor has a dedicated .gitignore panel. Saving preserves content whitespace
and existing version pinning. Cloud getting-started documentation describes the
workflow and the limits of ignore rules and local hooks.

Five local system structures received new versions with these templates. Existing
blueprint versions remain pinned. The normal BlueprintSeeder publishes the new
structure and blueprint versions when run during deployment.

Testing exposed SQLite losing the WHERE clause on the facets system-key index
following a table rebuild. A narrow SQLite-only repair migration restores the
partial index so a personal Course copy can share the system key. PostgreSQL is
unchanged. The template round-trip test covers copying Course, editing rules,
and leaving the source unchanged.

Validation: production asset build; focused library, structure, docs, builder,
API pieces/import, and ZIP download tests. Chrome checks passed in light and
dark at 1440px and 390px, including settings/install interactions, terminal icon
state, setup order, template preview, editor defaults, and page overflow.
Screenshots inspected. Local origin works; the public tunnel routes to its
expected Cloudflare Access gate at https://framework-cloud.pequod.dev.
