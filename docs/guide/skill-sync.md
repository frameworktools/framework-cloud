# Skill sync

Skill sync connects a local agent skill directory to your Framework Cloud account. Each version stores `SKILL.md` and its companion files while preserving binary contents and executable file permissions.

## Account connection

Authenticate in the package or CLI with your personal account token. Project pull tokens cannot publish skills. Sync communicates with the configured Cloud server.

## Local skills

Run skill sync from inside a Framework project using a project-relative skill directory:

```r
framework::skills_sync(".claude/skills/my-skill")
```

```python
import framework

framework.skills_sync(".agents/skills/my-skill")
```

```sh
fw skills sync .agents/skills/my-skill --variant python
```

Syncing an untracked skill creates a private account skill at version 1. Matching names do not link unrelated skills. Subsequent local edits publish new versions of that skill.

Unmodified built-in Framework skills keep their canonical identity. Editing a built-in skill locally creates a private fork linked to the exact canonical version. Forks retain provenance across renames, edits, and downstream forks.

## Status

Pass `dry_run = TRUE` in R, `dry_run=True` in Python, or `--dry-run` in `fw` to preview the next sync action. The `direction` parameter (`--direction` in `fw`) accepts `auto`, `push`, `pull`, and `status`.

Automatic sync pushes local edits or pulls Cloud updates when only one side changed. Sync stops when both sides changed or when copies written for multiple coding agents diverge or go missing.

## Conflicts

Resolve conflicts by editing the files manually or choosing one side explicitly. Passing `resolve = "local"` in R, `resolve="local"` in Python, or `--resolve local` in `fw` publishes the local snapshot. Passing `remote` installs the Cloud snapshot. Both options require an explicit sync call. Automatic line-level merging is unavailable.

Overwritten local directories are backed up to `.framework/skills-backups/`, which Git ignores. Previous Cloud versions remain available in the web skill editor, where restoring a snapshot publishes a new version whenever its contents differ from the latest version.

## Untracked skills

Downloading a skill for the first time requires an explicit skill ID (`skill_id` or `--id`) and an absent target directory. Select the language variant with `variant` or `--variant`. Adopting an untracked local directory requires its contents to match the Cloud snapshot. Matching directory names alone are rejected.

## Project state

`.framework/skills.lock.json` tracks skill identifiers, language variants, base hashes, and agent directory paths. Commit this lockfile with the project. R, Python, and `fw` read the same format, verify snapshot hashes before writing files, reject stale updates, and reuse saved request IDs when retrying interrupted uploads.

Only one local sync process runs at a time. If a sync process terminates unexpectedly, verify that no sync is active before deleting `.framework/skills.sync.lock`.

## File limits

Each skill allows up to 256 files, 4 MB per file, and 16 MB total. File paths allow ASCII letters, digits, periods, underscores, and hyphens. Symlinks, parent-directory traversals, `.git` metadata, `.env` files, private keys, and generated caches are excluded or rejected. `SKILL.md` must be a regular non-executable UTF-8 file of at most 64 KB.

Package skill updates preserve local edits and account forks. Sync runs only on explicit invocation.
