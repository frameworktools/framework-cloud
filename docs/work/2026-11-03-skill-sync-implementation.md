# Skill sync implementation

## Scope

The first implementation spans `framework-cloud`, `framework`, `framework-py`,
and `framework-cli`. Coordinated releases are published and deployed. See
[release verification](2026-11-03-coordinated-release.md) for versions, commits,
and production checks.

Cloud now stores stable skill identities, exact bundle snapshots, conditional
versions, request receipts, and source-version fork edges. Canonical snapshots
come from `scripts/sync-skill-bundles.py`. Run this script after changing a
shipped skill in any package. The registry includes R, Python, and Stata
payloads. Unchanged snapshots retain their release number.

The API uses account tokens. Project tokens cannot publish skills. Endpoints:

- `POST /api/v2/skills/sync`
- `GET /api/v2/skills/sync/{identity}`
- `GET /api/v2/skills/sync/{identity}/history`

Snapshot reads accept `variant`, `version`, and exact-source `hash`. Writes
require a UUID request ID and expected version. Forks preserve their exact
source version. Ordinary deletion retains version records through soft deletion.
Parent metadata respects visibility. Private descendants are never enumerated.

The web editor supports language variants, version history, and restoration.
Restoration creates a new version when the selected snapshot differs from the
current one. Identity and fork ancestry cannot change after creation.

## Package behavior

R exports `skills_sync()`. Python exports `framework.skills_sync()`. The CLI
provides `fw skills sync PATH`. All three share
`.framework/skills.lock.json` and exact-byte hash vectors. They use native HTTP
clients and existing account credentials. The packages do not require the CLI.

Snapshots include binary companions and executable modes. Paths are sorted
before hashing. The manifest starts with `framework-skill-bundle-v1\n`.
Each entry contains path, file SHA-256, mode (`644` or `755`), and byte count,
separated by tabs and terminated by a newline. SHA-256 of the manifest is the
bundle hash. `SKILL.md` is nonexecutable.

Clients compare local, base, and Cloud hashes. Divergence stops sync.
Explicit local or remote resolution selects a snapshot. Assistant aliases must
agree before sync. Directory installs are staged and verified. Replaced local
directories remain under ignored `.framework/skills-backups/`. A shared local
lock prevents simultaneous client operations. Interrupted uploads retain a
request ID and payload basis for retries.

Project creation records package provenance. Customized Cloud skills carry
identity and bundle metadata through layer/spec schemas. Package updates preserve
local edits and account-owned forks. The R schema generator remains the schema
source; generated schemas are copied to the other three repositories.

## Verification

Real HTTP verification used the local Herd origin and a temporary account token:

1. Python uploaded a skill with a binary companion as version 1.
2. R read the same project state and published version 2.
3. The CLI read that state and published version 3.
4. A browser edit published version 4. Python pulled it with unchanged binary bytes.
5. A concurrent Cloud edit and local edit produced a conflict. Explicit remote
   resolution pulled version 5 and retained the unpublished local bytes in a backup.
6. An unchanged Python package skill adopted its canonical identity. An edit
   created a private fork with the exact source-version parent.
7. Browser restoration created version 6. A subsequent editor save created
   version 7 with the restored content and updated base revision.

The editor was checked in light and dark desktop views and at a 390px phone
width. No horizontal page overflow occurred. Targeted tests cover hashing,
binary files, deletions, excluded files, retries, privacy, source forks, fork
chains, stale writes, language isolation, scaffolding, and conflict resolution.

The main preview is `https://framework-cloud.pequod.dev`. The local origin was
verified. Public routing reaches Cloudflare Access; that redirect alone does
not verify authenticated remote rendering.

The temporary verification account token was revoked and its plaintext file
removed. Targeted checks passed: 77 Cloud tests before the additional identity
immutability test, 88 Python tests, 235 R assertions, and the Go sync, Stata,
CLI, and spec tests. Python lint and strict type checks passed. The frontend
build passed. R reference documentation and all local GUI docs databases were
regenerated.

## Remaining scope

Sync is explicit. There is no background watcher, automatic text merge, upstream
merge UI, version diff viewer, bulk sync command, or automatic alias reconciliation.
Directory moves require updating the recorded path; paths never infer identity.
Version history currently lists versions and notes. Companion files can be
published through packages; the web editor edits `SKILL.md`.

Cross-client live tests ran on macOS. CLI CI passed on Windows, macOS, and
Linux. Python CI passed across Windows, Linux, and macOS on Python 3.11–3.14.
Windows installs retain portable
executable-mode metadata in the excluded `.framework-skill.json` file. Dedicated
R Windows verification remains future work. Crashed processes can leave a local lock directory;
the user must confirm no sync process remains before removing it.
