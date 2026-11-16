---
id: git
title: Git integration
section: features
position: 10
description: 'Git repository initialization, gitignore templates, pre-commit hooks, and R helpers'
---
## Overview

Framework initializes Git repositories during project creation, applies type-specific `.gitignore` templates, runs optional pre-commit safety hooks, and provides R helper functions for routine Git commands.

## .gitignore templates

Each project type includes a tailored `.gitignore` template:

- **project**: excludes `.env`, `framework.db`, caches, and temporary R files.
- **project_sensitive**: additionally excludes `inputs/private/` and `outputs/private/`.
- **course**: the project rules plus `rendered/` and `.aws/`.
- **presentation**: the project rules plus `.aws/`.

Edit `.gitignore` templates per project type on Framework Cloud.

## Commit identity and hooks

Configure Git identity in global Git config and pre-commit hooks in `settings.yml`.

### Commit identity

Configure `user.name` and `user.email` in global Git config so Framework can create the initial project commit automatically. When Git identity is unset, Framework initializes the repository, skips the initial commit, and prints a note.

### Pre-commit hooks

Enable pre-commit hooks with `git_hooks_enable()` or in `settings.yml`:

```yaml
git:
  hooks:
    data_security: true
    check_sensitive_dirs: true
    check_notebook_outputs: true
```

- **`data_security`**: runs `git_security_audit()` and blocks the commit when a check fails, such as tracked private data, tracked `.env` files, or passwords written into tracked connection settings.
- **`check_sensitive_dirs`** (or `warn_unignored_sensitive`): warns when directories named like `private`, `sensitive`, `cache`, or `scratch` are missing from `.gitignore`, and asks before continuing in an interactive terminal.
- **`check_notebook_outputs`**: blocks staged `.ipynb` notebooks that still contain cell outputs.

The 1.x `ai_sync` hook was removed in Framework 2.0.

## R helper functions

Run Git operations directly from the R console:

```r
git_status()
git_add()
git_add("file.R")
git_commit("Fix missing value filter")
git_push()
git_pull()
git_diff()
git_log()
```

Stage, commit, and push changes in sequence:

```r
git_add()
git_commit("Add data cleaning step")
git_push()
```
