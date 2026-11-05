# Publish drivers

Implemented the approved destination pipeline across Cloud, R, Python, and fw.
See `docs/plans/2026-11-04-publish-drivers.md` for the contract and remaining limits.

Cloud: 486 tests and 3,749 assertions passed. JavaScript: 10 tests passed.
R: 117 focused tests, 824 assertions, and four optional/integration skips.
Python: 521 full-suite tests passed with 41 optional/integration skips, followed
by passing focused settings, destination, and GitHub budget checks.
CLI: focused tests, race checks, vet, and asset-content checks passed. The
full-suite asset provenance gate requires a canonical R commit before release.

Production builds passed. All package reader templates match Cloud. Real
browser checks covered offline artifacts from all three clients, Cloud default
settings, artifact management, sharing, and a metadata-only folder publication.
Light, dark, and 390px layouts were inspected. Temporary metadata and QA tokens
were removed after browser checks.

Preview: https://framework-cloud.pequod.dev. Local origin returned 200. Public
routing returned the expected Cloudflare Access redirect. Authenticated public
routing was not verified. No commits, pushes, releases, or deployments.
