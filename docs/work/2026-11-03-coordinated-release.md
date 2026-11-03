# Coordinated release

## Published versions

| Product | Version | Source commit |
|---|---|---|
| Framework R | 1.9.0.9002, development prerelease | `c8bb65019fdad5eada84453e1ba88b1a35ddb113` |
| framework-py | 0.1.0.dev6, PyPI development release | `37ca6818ef947201a4e716dcb45cbc7e73af5077` |
| fw CLI | 0.4.2 | `f0580aa1b3f0fbbc55c3253cebe5e82d388723f8` |
| Framework Cloud | 0.2.0 | `76c1ca4a79d7a5352591fa248ce42bd8c38c5c4d` |

All releases are on GitHub. Python is on PyPI. The CLI workflow published all
six platform archives, checksums, documentation, and the Homebrew cask update.
R was released through GitHub; no CRAN submission was made.

Both `ghcr.io/frameworktools/framework-cloud:0.2.0` and
`ghcr.io/frameworktools/framework-scaffolder:0.2.0` support anonymous pulls.
Both contain amd64 and arm64 manifests. All image jobs completed successfully.

## Production

Forge deployment `81991661` activated the Cloud release at
`https://framework.pub`. All artifact, collection, report, and skill-sync
migrations ran. The canonical seeder completed with eight system skills.

The production scaffolder now runs the published
`ghcr.io/frameworktools/framework-scaffolder:0.2.0` image. Its health endpoint
reports the exact client commits above. R, Python, and Stata downloads were
built and checked for installed skills and `.framework/skills.lock.json`.
The previous container remains stopped for rollback.

Production GitHub scaffold pins match the release commits. CLI downloads use
the release checksums file unless an explicit checksum is configured.

Private local files now use `/home/forge/framework.pub/shared/private` through
`FW_LOCAL_STORAGE_ROOT`. Existing files were copied before deployment. The
database and environment were backed up under `/home/forge/backups` before
migration. Credentials were never printed. The Forge CLI server context was
restored to `apps` after deployment.

Release documentation was imported for all three clients. The public Cloud
sync guide and the R and Python sync reference pages returned HTTP 200.

## Live verification

Fresh installs used the published PyPI package, the R GitHub release tarball,
and the checksummed macOS CLI archive. A temporary production account performed
the following checks:

1. Python created a project through the public API and downloaded its scaffold.
2. Python uploaded a skill with a binary companion as version 1.
3. R read the same project state and published version 2.
4. The CLI read that state and published version 3.
5. Python verified the shared identity and unchanged binary bytes.
6. A downloaded canonical skill adopted its source identity. A customization
   became a private fork anchored to its exact source version.
7. Python published a pandas table. Its public reader rendered the structured
   table through the production private storage path.

The temporary account, project, skill records, uploaded artifact files, and API
tokens were removed after verification. Local plaintext verification token
files were removed too.

## Release checks

- Cloud: 474 PHP tests and seven JavaScript tests passed. Frontend build passed.
- Python: 499 local tests passed. Optional adapters and external staging tests
  without their dependencies or credentials were skipped. Lint, formatting,
  strict types, documentation, and shared asset checks passed. Windows 3.11–3.14
  and Linux CI passed. Remaining macOS matrix jobs were still finishing at the
  time of the production checks.
- CLI: full race tests, vet, and lint passed. GitHub CI passed on Windows,
  macOS, and Linux, including credential interoperability.
- R: full development tests passed. The release tarball passed R CMD check
  with one note for intentionally shipped canonical metadata files. GitHub
  database integration and documentation workflows passed.

The first CLI tag failed a stale assertion. An intermediate 0.4.1 release was
marked as superseded after Windows CI found replacement and mode issues.
Version 0.4.2 contains the fixes. Python 0.1.0.dev6 supersedes dev5 for Windows.
No published tags were moved.

The unrelated local `polyscope.json` edit was preserved and excluded from the
release commits.
