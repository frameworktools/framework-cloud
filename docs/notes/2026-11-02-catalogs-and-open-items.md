# Catalogs rework and what's open

Where things stood on 2026-11-02, after the catalog briefs. Everything is
committed and pushed in framework-cloud, framework (R, branch `v2`),
framework-py, and framework-cli. Nothing since 0c6e9f1 is deployed.

## Done (framework-cloud, not deployed)

- **Blueprint builder** previews the resolved tree for a chosen language and
  workflow (`POST /blueprints/preview`), and saves which languages and
  workflows a blueprint supports. Requirements carry over a save; a default
  workflow it no longer supports is dropped.
- **Blueprints catalog** browses by language (R, Python, Stata) and workflow.
  Each blueprint is its own card, and one per section is always open.
  - Previews come from the resolver with saved defaults
    (`POST /blueprints/{id}/preview`).
  - New project opens with the blueprint, language, and workflow chosen. The
    New project page gained a workflow choice.
  - Set as default (`POST /blueprints/{id}/default`) changes only the default
    blueprint and that language's workflow. It's for Framework blueprints
    only, since `defaults.project_type` holds a key.
  - Copy and edit carries the language and workflow into the copy's
    `default_workflow`.
- **Short names**: Standard, Privacy sensitive, Presentation, Course, and Bare
  (last) for both blueprints and structures, set in BlueprintSeeder.
- **Project structures**: rows that open to the structure's own files
  (`StructurePreview`), labeled R only, Python only, or with Quarto. Shows
  what renders where, and for sensitive structures what Framework enforces
  and what agents are told.
- **Package sets**: R and Python tabs, Add to defaults
  (`POST /package-sets/{id}/defaults`) adds only missing packages. Sets must
  name R or Python and can list `requirements`. A chosen set for another
  language is left out with a notice.
- **Skills**: `skills.language`, `skill_versions.variants` (per-language
  text), and `skills.summary` (from `metadata.summary`).
  - Python text comes from framework-py at d10c19f
    (`database/seeders/masters/skills/python/lock.json`).
  - The read view (`/skills/{id}?language=`) renders the whole skill, with
    Source and Copy source. Skill sets list their skills to read.
- **Save button** in SectionLayout sits under the menu and stays in view.
- **Empty Projects** page shows a tile that opens New project.

## Deploying

Production needs `php artisan migrate` and
`php artisan db:seed --class=BlueprintSeeder` for the new columns, names,
order, skill variants, and summaries. The v2 API now lists Bare last, so fw
and the packages may show a new order.

## Waiting on Erik

1. **Package-set memberships.** The proposal is in the conversation of
   2026-11-01. In short:
   - **R:** Minimal (no packages), Tidyverse (the nine core packages),
     Bayesian analysis (brms, posterior, bayesplot, loo, tidybayes; rstan
     backend; needs a C++ toolchain), Tidy OMOP (the DARWIN EU packages plus
     omock and duckdb), and OHDSI HADES (DatabaseConnector, SqlRender,
     CohortGenerator, FeatureExtraction, CohortMethod,
     PatientLevelPrediction; needs Java 8 or later).
   - **Python:** Minimal (no packages), Data analysis (numpy, scipy,
     matplotlib, seaborn, statsmodels), and Bayesian analysis (pymc, arviz,
     bambi).
   - **Not proposed:** a Python OMOP set (no coherent toolchain yet), the
     HADES umbrella package, and cmdstanr.

   Once approved, the sets go into the R package's `inst/sets/packages` and
   sync with `fw:sync-pieces`. The R sets' JSON needs a `requirements` field,
   which the seeder already reads.
2. **Python 3.12.** pymc, arviz, and bambi need Python 3.12 or later, but
   framework-py projects allow 3.11. Raise framework-py's floor, or have only
   that set require 3.12?
3. **Tidyverse and defaults.** The existing Tidyverse set lacks readr and
   tibble, and `ProjectDefaults::DEFAULT_PACKAGES['r']` includes `lme4`,
   which probably doesn't belong.
4. **Tunnel URL.** `FW_CLIENT_URL` in the local `.env` should be the current
   Polyscope tunnel address, so the token screen's commands work from a
   laptop. `demo.sharedwithexpose.com` returned Expose's 404.
5. **academic.page brief.** It was pasted here, probably meant for another
   session, and was never forwarded.

## Follow-ups

- **R masters match.** The R package committed the skill summaries (bdb1115).
  Cloud's `masters/skills/*.md` already match it byte for byte.
- **Skill variants for your own skills.** Cloud keeps per-language text, but
  the skill editor only edits the shared text and sets one language. Editing
  a variant isn't built.
- **Stata skills.** fw ships `framework-stata`, which Cloud doesn't list.
  The skill catalogs have R and Python tabs only.
- **Set as default for your own blueprints** would need `defaults.project_type`
  to hold a handle, which R reads too. It's left for Framework blueprints.
- **framework-cli** committed its framework-py passthrough (6fe39d5: fw passes
  `--offline` and `--workflow` to framework-py's create, and runs its adopt).
  The fw release tag still waits on Erik in the cli session.
- **Full test suite** hasn't run on these changes; focused runs passed.
