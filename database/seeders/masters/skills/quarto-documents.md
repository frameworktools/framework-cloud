---
name: quarto-documents
description: Use when creating or editing Quarto documents, including YAML options, executable cells, cross-references, citations, and rendering for R or Python.
metadata:
  title: Writing Quarto documents
  summary: Executable Quarto documents with stable references and reproducible renders.
---

# Writing Quarto documents

## Quarto documents

Read the document YAML and project `_quarto.yml` before changing output settings. Respect the existing engine and environment. Use executable cell syntax for the chosen language and unique labels for figures, tables, and cross-references.

Keep computations in dependency order. Render from a fresh process to expose hidden state. Use project-relative input paths and avoid machine-specific working directories. Do not embed credentials in YAML or code cells.

Use `fig-` and `tbl-` labels for references and meaningful captions that identify units and uncertainty. Keep citations in the project's bibliography. Preview the target format; HTML success does not establish that PDF or Word output renders correctly.

Use caching or frozen execution only when its invalidation rules match the workflow. After changing data, parameters, or code dependencies, verify that affected results execute again. Render with `quarto render` from the project context and inspect warnings, missing references, and the final output.
