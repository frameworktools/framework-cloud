---
id: functions
title: Functions
section: features
position: 5
description: 'Organize reusable R functions sourced automatically by scaffold()'
---
## Overview

Framework automatically sources `.R` files in `functions/` during `scaffold()`, making project helper functions available across every notebook and script without manual `source()` statements.

## Directory layout

Place reusable R function definitions in `functions/`:

```
my-project/
├── functions/
│   ├── data_cleaning.R
│   ├── plotting.R
│   └── stats_helpers.R
├── notebooks/
└── scripts/
```

Calling `scaffold()` sources every `.R` file in `functions/`:

```r
library(framework)
scaffold()

clean_data <- my_cleaning_function(raw_data)
```

Keep only function definitions inside `functions/*.R` so `scaffold()` does not execute side effects on startup.

## Directory configuration

Override the default `functions/` path in `settings.yml`:

```yaml
directories:
  functions: R
```

Or list multiple function directories to source in order:

```yaml
directories:
  functions:
    - functions
    - helpers
    - lib
```
