---
id: viewing-work
title: Object and result inspection
section: features
position: 8
description: 'Inspect R objects, saved results, and project directories with view() and the GUI'
---
## Overview

Framework includes interactive viewers for inspecting R data frames and nested lists alongside query functions for saved project outputs.

## Interactive object viewer

Call `view()` to open an R object in an interactive browser view.

### Data frames

Data frames open in a searchable, sortable, and filterable table:

```r
view(mtcars)
```

![Data frame viewer](/images/view-dataframe.png)

Table controls support full-table search, per-column filters, header sorting, pagination, and clipboard, CSV, or Excel export.

### Nested lists

Lists and hierarchical R objects display YAML and R structure tabs:

```r
x <- list(a = 1, b = c(2, 3), c = list("D", "E", list(F = "G")))
view(x)
```

![List viewer - YAML tab](/images/view-list-yaml.png)

![List viewer - R tab](/images/view-list-r.png)

### Viewer options

```r
# Custom window title
view(df, title = "Survey Results")

# Row cap for large tables
view(large_df, max_rows = 1000)
```


## Results queries

List outputs recorded in `framework.db` with `result_list()`:

```r
# All saved outputs
result_list()

# Filter by artifact type
result_list(type = "figure")
result_list(type = "table")
result_list(type = "model")

# Filter by public visibility
result_list(public = TRUE)
result_list(public = FALSE)

# Combined filters
result_list(type = "table", public = TRUE)
```

`result_list()` returns a data frame of tracked outputs and their metadata.

## Project summary

Print the project name and type, directory mappings, cache path, and `save_*()` output folders with `project_info()`:

```r
project_info()
```

## Graphical browser

`framework::gui()` is deprecated, and the local project browser is retired.
