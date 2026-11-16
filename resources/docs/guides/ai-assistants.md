---
id: ai-assistants
title: AI assistants
section: features
position: 11
description: 'Generate and synchronize project context files for AI coding assistants'
---
## Overview

Framework generates and updates instruction files for AI coding assistants with your project's directory layout, data catalog entries, and installed packages.

## Supported assistants

Framework writes three instruction file formats:

- **Claude Code**: `CLAUDE.md` (points to `AGENTS.md`)
- **GitHub Copilot**: `.github/copilot-instructions.md` (points to `AGENTS.md`)
- **Multi-agent tools** (Codex, Cursor, and others): `AGENTS.md`

Select target assistants under `ai.assistants` in `settings.yml` or in your Framework Cloud defaults.

![AI Assistants GUI](/images/ai-assistants-gui.png)

## Canonical context file

`AGENTS.md` is the canonical file (`ai.canonical_file` in `settings.yml`). Framework always creates `AGENTS.md`; `CLAUDE.md` and `.github/copilot-instructions.md` only point to it, so nothing needs synchronization.

## Context templates

Generated context files document:

- Framework function conventions (`scaffold()`, `data_read()`, and `save_*()`).
- Project directory paths.
- Data catalog keys and file formats from `settings.yml`.
- Configured packages.
- Project-type rules, such as `private/` and `public/` boundaries.

Sections marked with `@framework:regenerate` update when you run `ai_regenerate_context()` (and `package_add()` refreshes the packages section) while leaving custom notes outside those sections intact.

## File synchronization

The 1.x `ai_sync` hook was removed in Framework 2.0, and `ai_sync_context()` is deprecated. Leftover `ai_sync` settings are ignored.

## Context regeneration

Refresh `@framework:regenerate` sections manually in R after modifying directories, packages, or data catalog entries:

```r
ai_regenerate_context()
```
