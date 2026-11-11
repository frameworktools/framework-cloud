---
id: packages
title: Packages
section: features
position: 3
description: 'Managing R packages in Framework projects'
---
## Overview

Framework manages your project's R package dependencies through `settings.yml`. When you call `scaffold()`, it installs missing packages and loads packages marked `auto_attach: true`.

## Language support

Auto-attach is an R feature. It calls `library()` when `scaffold()` runs. Python and Stata do not implement this option.

In Python, install project dependencies with uv or pip and write explicit imports in each notebook or script, such as `import pandas as pd`. Python's `scaffold()` does not auto-import those dependencies into the calling notebook or script.

Stata supports add-on packages, but Framework's Stata setup has no auto-attach or auto-import setting.

## Blueprint package choices

For an R blueprint, choose package sets, then review the individual packages below. Use each selected package's **Auto-attach** switch to control whether `scaffold()` loads it. Turning auto-attach off keeps the package selected for installation. Sets supply the initial value; individual switches override it. Python and Stata blueprints do not show these switches.

## Configuring Packages

In your `settings.yml`, or through **Packages** in `framework::gui()`, you can curate the defaults. The GUI lets you search CRAN with autocomplete, and you can switch the source dropdown to GitHub or Bioconductor when you need development builds.

![Default package configuration UI with source selector and auto-attach toggles](/images/packages-defaults.png)

In your `settings.yml`:

```yaml
packages:
  - name: dplyr
    auto_attach: true
  - name: ggplot2
    auto_attach: true
  - name: jsonlite
    auto_attach: false
```

### auto_attach

- `true`: package is loaded automatically when you call `scaffold()`
- `false`: package is installed but not loaded (use `library()` when needed)

## Package management

When `scaffold()` runs:

1. Checks which packages are missing
2. Installs missing packages
3. Loads packages with `auto_attach: true`

You don't need to write `library()` calls for attached packages.

## Version Pinning

Pin specific versions or install from GitHub:

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

## renv Integration

Framework detects if your project uses [renv](https://rstudio.github.io/renv/) and routes installations through it automatically.

To enable renv in a new project, configure it in your defaults via `setup()` or enable it per-project:

```r
renv::init()
```

When renv is active:
- Packages install to the project library
- `renv.lock` tracks exact versions
- Projects are isolated from your global library


---

<div style="display: flex; justify-content: space-between">

[← framework.db](/docs/framework-db)

[Data Management →](/docs/data-management)

</div>
