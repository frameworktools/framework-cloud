---
name: identifiable-data
description: Use when an analysis handles identifiable, confidential, or restricted data, including logs, summaries, intermediate files, and output disclosure.
metadata:
  title: Working with identifiable data
  summary: Limit disclosure in analysis files, logs, and shared results.
---

# Working with identifiable data

## Data disclosure

Establish which fields identify people directly or through combinations and which data-use rules govern the project. Keep access limited to the authorized environment. Do not send records to external services without authorization.

Use counts, schemas, and aggregate diagnostics to investigate data. Avoid printing identifiable rows, free text, tokens, or direct identifiers into notebooks, terminal logs, screenshots, or error reports. Synthetic examples should not preserve distinctive real records.

Check intermediate files, caches, model objects, and notebook outputs as well as final tables. Model objects can retain source data or identifying attributes. Remove unnecessary identifiers from analysis datasets while preserving linkage separately when needed.

Apply the project's disclosure rules to small cells and combinations of tables. Suppress complementary cells when totals would reveal a suppressed value. Do not invent a universal suppression threshold. Review outputs before sharing and record the checks performed. Framework folder conventions are covered separately by framework-sensitive-data when installed.
