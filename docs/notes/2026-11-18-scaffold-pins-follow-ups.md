# Scaffold pins and failure reasons: follow-ups

Date: 2026-11-18.

## Done

- Cloud offers only package skills the pinned packages ship (1ebaa2c). `database/seeders/masters/package-skills.json` records them, from `php artisan fw:sync-package-skills`. PackageSkillsTest fails when a pin and the record disagree.
- Retired package skills become their successors (`PackageSkills::SUCCESSORS`) in stored projects and old piece versions.
- A failed GitHub scaffold records the failing step and its error lines. Retry re-commits the current layers and workflow.
- Pins: framework 2.0.13 (76a587f), framework-py 0.1.0.dev26 (af8a9f7), fw 0.6.6 (4e71c33). Production's `.env` sets the same, with `GITHUB_FW_SHA256` for the fw 0.6.6 Linux archive.
- The production scaffolder runs `framework-scaffolder:fw066`. The stopped `framework-scaffolder-76a587f` container is the rollback. Old images are pruned.
- erikwestlund/bayesian-social-statistics-r scaffolded on retry (run 44335499755).

## Open

- GitHub's `workflow_run` webhooks seem not to arrive. The endpoint accepts a signed test (204), but the bayesian retry was recorded by `fw:refresh-scaffolds`, the every-minute scheduler. Production has no GitHub App id or private key, so the App's delivery log can't be read from the server. Check the App's webhook URL, secret, and recent deliveries in GitHub's App settings.
- No real Stata scaffold on GitHub has run with fw 0.6.6 yet. The zip scaffolder built a Stata project with it. Run one as the examples account in `frameworktools` to confirm.
- On each pin bump: update `config/services.php`, the scaffolder Dockerfile, and production's `.env`, run `fw:sync-package-skills`, and rebuild the scaffolder image on the box.
