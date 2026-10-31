# Pinned spec schemas

These files are copied from the framework R package, where
`data-raw/spec_schema.R` generates them. Do not edit them here.

| File | Validates | Source in the R package |
| --- | --- | --- |
| `layer.v1.json` | One stored layer (base, facet, blueprint) | `inst/schema/layer.v1.json` |
| `layers.v1.json` | The layers envelope: `.framework/layers.json` and the pull v2 body | `inst/schema/layers.v1.json` |
| `spec.v1.json` | A resolved project spec: `.framework/spec.json`, sent by `PUT /api/v2/project/spec` | `inst/schema/spec.v1.json` |
| `docs-reference.v1.json` | A docs site's `reference.json`, checked by `fw:docs-import` | `inst/schema/docs-reference.v1.json` |

`docs-site.v1.json` is the cloud's own schema for a docs site's `site.yml`. It
follows the format in the R package's `inst/schema/docs-site.md` and is not
copied or locked.

`schema.lock.json` records the R package commit they came from and each
file's SHA-256. To update them, copy them from a newer commit:

```bash
php artisan fw:sync-schemas ../framework <full commit SHA>
```

The command rewrites both files and the lock. `tests/Feature/SchemaLockTest.php`
fails when a file no longer matches its lock entry.

When the commit changes, also check `GITHUB_FRAMEWORK_SHA`'s default in
`config/services.php`. It is the R package commit that GitHub scaffolding
installs, and it usually moves with the schemas.
