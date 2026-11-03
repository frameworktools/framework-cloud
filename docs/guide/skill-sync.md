# Skill sync

Skill sync connects a local skill directory to your Framework Cloud account.
Each version contains `SKILL.md` and its companion files. Binary files and
executable scripts retain their bytes and modes.

## Account connection

Sign in through the package or CLI with your account token. Project pull tokens
cannot publish skills. Sync uses the configured Cloud server.

## Local skills

Run sync from a Framework project. Use a project-relative skill directory.

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

An untracked skill becomes a private account skill at version 1. Its name does
not establish a relationship to another skill. Later edits create versions of
the same skill.

Unchanged Framework skills retain their canonical identity. A local edit becomes
a private fork linked to the exact canonical version. Forks retain their source
when renamed or edited. Forks can also have forks.

## Status

Use `dry_run = TRUE` in R, `dry_run=True` in Python, or `--dry-run` in the CLI
to inspect the next action. The `direction` option accepts `auto`, `push`,
`pull`, and `status`. The CLI uses `--direction`.

Automatic sync pushes local changes or pulls Cloud changes when only one side
changed. It stops when both sides changed. It also stops when tracked copies
for different assistants diverge or disappear.

## Conflicts

Reconcile the files manually, or explicitly select a side. `resolve = "local"`
in R, `resolve="local"` in Python, or `--resolve local` in the CLI publishes
the local snapshot. The `remote` choice installs the Cloud snapshot. Both
choices require an explicit sync call. Automatic text merging is unavailable.

Replaced local directories remain in `.framework/skills-backups/`. This
directory is ignored by Git. Cloud versions remain available in the skill
editor. Restore publishes the selected snapshot as the current version when
its content differs from the current snapshot.

## Existing skills

An initial download requires an explicit skill ID and an absent target directory.
Use `skill_id` in the packages or `--id` in the CLI. Select the language with
`variant` or `--variant`. An existing untracked directory must match the Cloud
snapshot before adoption. Its name alone is insufficient.

## Project state

`.framework/skills.lock.json` records identities, language variants, base hashes,
and assistant paths. Keep it with the project. R, Python, and the CLI share this
format. They verify snapshots before installing them and reject stale writes.
Repeated requests reuse a saved request ID after an interrupted upload.

Only one local sync can run at a time. After a terminated process, confirm no
sync is running before removing `.framework/skills.sync.lock`.

## File limits

Each skill supports 256 files, 4 MB per file, and 16 MB total. Paths use ASCII
letters, numbers, periods, underscores, and hyphens. Symlinks, parent paths,
Git metadata, environment files, private keys, and generated caches are excluded
or rejected. `SKILL.md` must be a regular, nonexecutable file.

Package skill updates preserve local edits and account forks. Sync is explicit;
background synchronization is unavailable.
