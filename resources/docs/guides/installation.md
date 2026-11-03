---
id: installation
title: Installation
section: getting_started
position: 1
description: 'Framework installation'
---
## Requirements

- **R** version 4.1 or higher.
- Any editor, including the console. 

Framework works well with [Positron](https://positron.posit.co/) (we built Framework with it!), [VS Code](https://code.visualstudio.com/), and [RStudio](https://posit.co/products/open-source/rstudio/)

## GitHub installation

Framework is currently only available via GitHub. Install using the `remotes` package:

```r
install.packages("remotes")
remotes::install_github("frameworktools/framework")
```

## Installation verification

After installation, verify Framework is working:

```r
library(framework)
packageVersion("framework")
```

You should see the version number displayed.

## Updating Framework

To update to the latest version:

```r
remotes::install_github("frameworktools/framework")
```

---

<div style="display: flex; justify-content: flex-end">

[Setup →](/docs/setup)

</div>
