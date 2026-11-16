---
id: installation
title: Installation
section: getting_started
position: 1
description: 'Install and update the Framework R package'
---
## Requirements

- R version 4.1 or later.
- Any R editor or terminal console, including [Positron](https://positron.posit.co/), [VS Code](https://code.visualstudio.com/), and [RStudio](https://posit.co/products/open-source/rstudio/).

## GitHub installation

Install Framework from GitHub with the `remotes` package:

```r
install.packages("remotes")
remotes::install_github("frameworktools/framework")
```

## Installation verification

Load Framework and inspect the installed version:

```r
library(framework)
packageVersion("framework")
```

R prints the active package version number.

## Package updates

Re-run `install_github()` to update to the latest commit on `main`:

```r
remotes::install_github("frameworktools/framework")
```
