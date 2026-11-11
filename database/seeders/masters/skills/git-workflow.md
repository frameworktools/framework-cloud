---
name: git-workflow
description: Use when reviewing changes, committing analysis work, resolving merge conflicts, or coordinating branches in a research repository.
metadata:
  title: Using Git in an analysis project
  summary: Reviewable Git changes for analysis code and project files.
---

# Using Git in an analysis project

## Git changes

Inspect the current branch, status, and diff before editing or committing. Preserve unrelated work. Stage explicit paths so generated output, raw data, credentials, and machine-specific files are not included accidentally.

Keep commits focused on a coherent change. Review the staged diff and run checks appropriate to the affected work. Describe the changed behavior and relevant validation in the commit or review description.

For notebook conflicts, compare cell content and metadata using notebook-aware tools when available. Re-execute affected analyses after resolving conflicts. For generated files, establish which source produced them before choosing a side.

Do not rewrite shared history or overwrite local work to simplify synchronization. Inspect divergence before merging or rebasing. Follow the user's authorization for commits, pushes, and publication. A request to analyze data does not authorize publishing its repository or outputs.
