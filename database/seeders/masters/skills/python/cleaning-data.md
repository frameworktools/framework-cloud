---
name: cleaning-data
description: Use when transforming raw observations into analysis data, including parsing, recoding, joins, reshaping, and missing-value decisions.
metadata:
  title: Cleaning data
  summary: Documented transformations from raw observations to analysis data.
---

# Cleaning data

## Transformations

Keep raw inputs unchanged. Write transformations in execution order and save the analysis data separately. State the unit of observation and expected key before joining or reshaping.

Preserve source identifiers as strings when leading zeros matter. Distinguish absent, unknown, refused, and structurally inapplicable values when the source does. Record recoding rules beside the code. Do not infer units or missing-value codes from a few rows.

Before a join, establish its cardinality. Count unmatched keys on both sides and compare row counts before and after. Resolve duplicate keys explicitly; dropping duplicates is a substantive decision. For repeated observations, include the time or episode identifier in the key.

Keep a small record of exclusions and row counts at each consequential step. Data validation and assertions belong in checking-data when available.

## Language conventions

Set explicit dtypes on import. Use `merge(validate=..., indicator=True)` to verify pandas joins. For Polars, check the join validation and null behavior supported by the installed version.
