---
id: caching
title: Caching
section: features
position: 12
description: 'Cache long-running R computations to disk with expiration and hash tracking'
---
## Overview

Framework's cache helpers serialize computation outputs as `.rds` files in the project cache directory and record creation timestamps, expiration rules, and SHA-256 checksums in `framework.db`. For external data sources used inside cached expressions, see [Connections](/docs/connections).

| Project type | Default cache directory |
|---|---|
| `project` | `outputs/cache` |
| `project_sensitive` | `outputs/private/cache` |
| `course` | `cache` |
| `presentation` | `cache` |

Framework creates the cache directory on the first cache write. Override the path in `framework::setup()` under **Project Defaults** or in `settings.yml`:

```yaml
directories:
  cache: my/cache/path
```

## Memoized evaluation

Wrap an expression in `cache_remember()` to evaluate and save on the first call and read from disk on subsequent calls:

```r
# First call evaluates the block and writes outputs/cache/my_analysis.rds
processed_data <- cache_remember("my_analysis", {
  raw_data |>
    mutate(date = parse_date(date_string)) |>
    filter(valid == TRUE) |>
    group_by(category) |>
    summarize(total = sum(amount))
})

# Subsequent calls return the stored value without evaluating the block
processed_data <- cache_remember("my_analysis", {
  raw_data |>
    mutate(date = parse_date(date_string)) |>
    filter(valid == TRUE) |>
    group_by(category) |>
    summarize(total = sum(amount))
})
```

## Cache expiration

Pass `expire_after` as numeric hours or a duration string to invalidate stale entries:

```r
# Expires after 1 hour
fresh_data <- cache_remember("live_data", {
  fetch_from_api()
}, expire_after = 1)

# Duration strings
cache_remember("daily", expr, expire_after = "1 day")
cache_remember("weekly", expr, expire_after = "7 days")
```

## Forced recomputation

Pass `refresh = TRUE` to ignore an existing entry and overwrite it:

```r
updated <- cache_remember("my_analysis", {
  expensive_computation()
}, refresh = TRUE)
```

## Direct cache storage

Store an already-computed R object with `cache()`:

```r
cache("processed_results", my_data)
cache("temp_results", my_data, expire_after = 24)
```

## Cache retrieval

Read a cached object by key with `cache_get()`, which returns `NULL` when absent or expired:

```r
cached <- cache_get("processed_results")
```

## Cache deletion

Delete one cache entry with `cache_forget()`:

```r
cache_forget("my_analysis")
```

## Cache inventory

List cached entries with creation times, expiration timestamps, and status:

```r
cache_list()
```

## Cache cleanup

Remove all cached files and metadata entries in the project:

```r
cache_flush()
```

## Usage conventions

### Descriptive keys

Name cache keys after the dataset, scope, and step:

```r
# Specific key
cache_remember("sales_2024_by_region", ...)

# Ambiguous key
cache_remember("data1", ...)
```

### Target workloads

Reserve caching for multi-second queries, simulations, or model fits rather than trivial arithmetic:

```r
results <- cache_remember("quarterly_report", {
  db_query("SELECT region, SUM(total) FROM orders GROUP BY region", "analytics")
})
```

### External data expiration

Set `expire_after` whenever caching mutable upstream APIs or live tables:

```r
api_data <- cache_remember("exchange_rates", {
  fetch_rates_from_api()
}, expire_after = "1 hour")
```
