---
name: checking-data
description: Use when validating an analysis dataset, testing join assumptions, or investigating inconsistent counts, ranges, keys, and missingness.
metadata:
  title: Checking data
  summary: Assertions for keys, ranges, missingness, and analysis samples.
---

# Checking data

## Validation

Derive checks from the analysis contract: unit of observation, key, measurement units, valid categories, and time coverage. A successful script run does not establish these properties.

Check key uniqueness and required nonmissing values. Summarize missingness by variables and relevant groups. Test impossible values separately from plausible outliers. Do not delete an outlier solely because a statistical rule flags it.

After joins, verify expected cardinality, unmatched keys, and row counts. After reshaping, compare group totals and the number of repeated measurements. For filtering, record the eligible population, exclusions, and final sample.

Use assertions for invariants that must hold and summaries for conditions needing judgment. Report failed checks with counts and variable names. Avoid printing sensitive records. Keep validation code with the transformation or analysis that depends on it.

## Language conventions

Use `stopifnot()` or the project's assertion tools for invariants. `anyDuplicated()` detects repeated keys; check missing key values separately.
