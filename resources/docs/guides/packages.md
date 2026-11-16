---
id: packages
title: Packages
section: features
position: 3
description: 'Manage R package installation, attachment, version pinning, and renv'
---
## Overview

Framework manages R package dependencies through `settings.yml`. Calling `scaffold()` installs missing packages and attaches packages configured with `auto_attach: true`.

## Language behavior

Package auto-attachment (`auto_attach: true`) is specific to R and calls `library()` during `scaffold()`.

In Python projects, install dependencies with `uv` or `pip` and write explicit `import` statements in each notebook or script. In Stata projects, manage community packages with `fw_packages`. Stata has no auto-attach setting.

## Blueprint package selection

On R blueprints, select package sets and toggle individual **Auto-attach** switches to control whether `scaffold()` calls `library()` on each package. Turning **Auto-attach** off keeps the package installed without attaching it automatically. Package sets supply initial `auto_attach` values that individual switches can override. Python and Stata blueprints omit auto-attach switches.

## Package configuration

Configure packages in `settings.yml` or with `package_add()`, which accepts CRAN, GitHub, and Bioconductor packages:

![Default package configuration UI with source selector and auto-attach toggles](/images/packages-defaults.png)

Example `settings.yml`:

```yaml
packages:
  - name: dplyr
    auto_attach: true
  - name: ggplot2
    auto_attach: true
  - name: jsonlite
    auto_attach: false
```

### auto_attach values

- `true`: installed if missing and attached via `library()` during `scaffold()`.
- `false`: installed if missing without attaching (call `pkg::fn()` or `library()` as needed).

## Scaffold execution

During `scaffold()`, Framework checks installed packages against `settings.yml`, installs missing packages, and attaches packages marked `auto_attach: true`.

## Version pinning and GitHub remotes

Pin specific CRAN versions or install development packages from GitHub:

```yaml
packages:
  - name: dplyr
    version: "1.1.0"
    auto_attach: true

  - name: mypackage
    source: github
    repo: "username/mypackage"
    auto_attach: true

  - name: devpackage
    source: github
    repo: "username/devpackage"
    ref: "develop"
    auto_attach: false
```

## renv integration

When [renv](https://rstudio.github.io/renv/) is enabled with `renv_enable()`, Framework routes package installation through `renv` automatically. Enable `renv` in your Framework Cloud defaults or initialize it inside an existing project:

```r
renv_enable()
```

With `renv` active, packages install into the isolated project library and `renv.lock` records exact package versions.
