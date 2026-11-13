---
name: quarto-documents
description: Use when creating or editing Quarto documents, including YAML options, executable cells, cross-references, citations, and rendering for R or Python.
metadata:
  title: Writing Quarto documents
  summary: Executable Quarto documents with stable references and reproducible renders.
---

# Writing Quarto documents

## Setup

- Read the document's YAML and the project's `_quarto.yml` before changing
  output settings. Keep the existing engine.
- Use project-relative paths.
- Keep credentials out of YAML and code cells.

## Cells and references

- Order cells so each runs after what it depends on.
- Label figures and tables with `fig-` and `tbl-` prefixes for
  cross-references, and give each a caption.
- Keep citations in the project's bibliography.

## Rendering

- Render with `quarto render`, and check the warnings, missing references,
  and output.
- Render each target format. An HTML render does not test PDF or Word output.
- With caching or `freeze`, confirm that changed code and data run again.
