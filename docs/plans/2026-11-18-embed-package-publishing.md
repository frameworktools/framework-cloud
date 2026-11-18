# Embed package publishing

The `framework-embed` repository ships through GitHub releases only. The WordPress plugin installs from its release zip. Every other package is unpublished, so integrators clone the repository, use Composer path or package repositories, or load the built files from a release, jsDelivr, or GitHub Pages. The guides document these routes. This plan lists what publishing each package would take. None of these steps has been taken.

## Packages

| Package | Registry | Name in the repository | Status |
|---|---|---|---|
| JavaScript renderer | npm | `@framework-cloud/embed` (`js/package.json`) | Unpublished. The `@framework-cloud` npm organization does not exist. |
| PHP renderer | Packagist | `framework-cloud/embed` (`php/composer.json`) | Unpublished. |
| Statamic addon | Packagist, Statamic Marketplace | `framework-cloud/statamic-embed` | Unpublished. Requires `framework-cloud/embed`. |
| Drupal module | drupal.org | `drupal/framework_embed` | No drupal.org project. Requires `framework-cloud/embed`. |
| WordPress plugin | wordpress.org | `framework-embed` | Distributed as a release zip. The wordpress.org submission is tracked separately. |

## Required work

1. **Versions.** `js/package.json` says `0.1.0`, and the Drupal and Statamic `composer.json` files require `framework-cloud/embed` `^0.1`, while releases are at 0.4.x. `php/composer.json` sets no version. Set one version across all packages and bump it in the release steps. The Composer path-repository instructions in the READMEs alias `0.1.0` and need updating at the same time.
2. **npm.** Create the `@framework-cloud` organization, or choose a scope owned by the `frameworktools` account. Publish `js/` with `npm publish --access public` after `make build`. `files` already limits the tarball to `dist`, `src`, `index.d.ts`, and `README.md`. Add `repository`, `homepage`, and `bugs` fields. Once published, jsDelivr and unpkg serve versioned files from npm, and the GitHub Pages copies can stay for the share dialog snippet.
3. **Packagist for `framework-cloud/embed`.** Packagist reads `composer.json` at the repository root. Either add a split repository, such as `frameworktools/framework-embed-php` kept in sync by a subtree split on each tag, or move the PHP package's `composer.json` to the root with `autoload` pointing at `php/src`. A root `composer.json` would make the WordPress, Drupal, and Statamic directories part of every install unless `archive.exclude` removes them. A split repository avoids those exclusions. Register the package under the `frameworktools` account and add the GitHub webhook.
4. **Packagist for the Statamic addon.** It needs its own split repository for the same reason. The Statamic Marketplace lists addons from Packagist.
5. **drupal.org.** Drupal modules publish through a drupal.org project with its own Git repository on git.drupalcode.org. That requires a drupal.org account, a project page, and pushing the `drupal/` directory as the project's repository. The packages.drupal.org Composer endpoint then serves `drupal/framework_embed`. It resolves `framework-cloud/embed` from Packagist, so step 3 comes first. Security coverage requires an opt-in review.
6. **Release steps.** Add the npm publish and the split-repository pushes to the release steps after tagging, so all registries move with each tag.

## Order

The PHP package on Packagist unblocks the Drupal and Statamic installs. npm is independent. The suggested order is versions, Packagist split for the PHP package, npm, Statamic split, then drupal.org.

After each package is published, replace its clone-and-path-repository instructions in `README.md`, `drupal/README.md`, `statamic/README.md`, and `docs/guide/embed-integrations.md` with a plain `composer require` or `npm install`.
