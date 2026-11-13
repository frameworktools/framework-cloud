---
name: jupyter-notebooks
description: Use when creating or editing Jupyter notebooks, including kernel selection, execution order, reproducibility, and output review for R or Python.
metadata:
  title: Working in Jupyter notebooks
  summary: Notebooks that execute in order with the intended kernel.
---

# Working in Jupyter notebooks

## Editing

- Check the notebook's kernel and the project environment before editing.
- Edit notebooks with notebook-aware tools, which keep the JSON valid. Keep
  cell ids and metadata.

## Execution order

- Arrange cells in the order they run: setup and imports, data loading,
  transformations, analysis, and output.
- Keep text cells next to the results they explain.
- Restart the kernel and run all cells to confirm the notebook runs in order.
- Use project-relative paths, and set a seed when randomness affects reported
  results.

## Outputs

- Follow the repository's policy on keeping or clearing outputs.
- Save large data and binary outputs as files, and refer to them.
- Check outputs for credentials before sharing.
