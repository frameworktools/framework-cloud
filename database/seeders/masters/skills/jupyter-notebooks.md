---
name: jupyter-notebooks
description: Use when creating or editing Jupyter notebooks, including kernel selection, execution order, reproducibility, and output review for R or Python.
metadata:
  title: Working in Jupyter notebooks
  summary: Notebooks that execute in order with the intended kernel.
---

# Working in Jupyter notebooks

## Notebook execution

Check the notebook's kernel metadata and project environment before editing. Preserve cell identifiers and notebook metadata unless the format requires a change. Use notebook-aware tools or valid notebook JSON; do not manipulate cells with textual substitutions that can corrupt the file.

Organize cells in execution order. Put configuration and imports near the start, then input loading, transformations, analysis, and output. Keep narrative cells close to the results they explain.

Restart the kernel and execute all cells to verify reproducibility. A notebook that works only after out-of-order execution needs correction. Inspect warnings and outputs as well as execution status. Use project-relative paths and make randomness reproducible when it affects reported results.

Review outputs for sensitive records and credentials before sharing. Follow repository policy for retaining or clearing outputs. Avoid large embedded data or binary outputs when a saved artifact and a reference suffice. Report execution limitations if the required kernel or data is unavailable.
