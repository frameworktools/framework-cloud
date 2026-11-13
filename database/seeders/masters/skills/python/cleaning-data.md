---
name: cleaning-data
description: Use when turning raw data into analysis data, including parsing, recoding, joins, reshaping, missing values, and checks on keys, ranges, and samples.
metadata:
  title: Cleaning data
  summary: Documented, checked steps from raw data to analysis data.
---

# Cleaning data

## Transformations

- Keep raw inputs unchanged. Save analysis data to a separate file.
- Write transformations in the order they run.
- Keep identifiers as strings when leading zeros matter.
- Keep the source's distinctions among missing values, such as unknown,
  refused, and not applicable.
- Take units and missing-value codes from the source's documentation.
- Record recoding rules beside the code.

## Joins and reshaping

- State the unit of observation and the key before joining or reshaping.
- Set the expected cardinality of a join, such as one-to-one or
  many-to-one. In pandas, pass `validate` and `indicator=True` to `merge()`, such as `validate="m:1"`.
- Count unmatched keys on both sides, and compare row counts before and
  after.
- Resolve duplicate keys explicitly. Dropping duplicates changes the sample.
- For repeated observations, include the time or episode in the key.
- After reshaping, compare group totals and the number of measurements.

## Checks

- Check that keys are unique and required values are present.
- Summarize missing values by variable and by relevant groups.
- Check impossible values separately from unusual ones. Remove an outlier
  only for a substantive reason.
- Use assertions for conditions that must hold, and summaries for conditions
  that need judgment.
- Record exclusions and row counts at each step that changes the sample.
