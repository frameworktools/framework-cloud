# Bidirectional skill sync design

Status: first implementation built across Framework Cloud, R, Python, and the CLI. See [implementation notes](2026-11-03-skill-sync-implementation.md) for verified behavior and remaining scope. The sections below retain the design rationale and future requirements.

## Existing foundations and gaps

Cloud already has account-owned skills, immutable SkillVersion records, language variants, companion files, and a fork operation that records the source version in forked_from_id. HasVersions deduplicates identical content using canonical JSON comparison. These should be extended rather than replaced by a parallel settings blob or version store.

R and Python already synchronize an account settings document using a saved base revision and hash. The Cloud settings endpoint rejects stale base revisions with HTTP 409. That is useful precedent, but it does not synchronize installed skill directories. The CLI config Sync routine only copies connection/token files between config directories.

The skills discovery endpoint exposes handles and version numbers, and system skill layer entries resolve to the installed package copy. The installation process therefore needs provenance for the exact package payload actually installed, not just Cloud's current system skill version. R's ai_skills_update overwrites installed Framework SKILL.md files; Python's equivalent replaces package-managed skill trees. Protecting locally changed files is an early dependency.

## Identity and ownership

- Give each logical skill a stable public ID, scoped to its server. Names, handles, titles, and installation paths can change without creating a new identity.
- Keep one skill to many immutable versions. Retain existing source-version fork provenance, expose it unambiguously, and record the base version of subsequent edits/merges.
- A hash identifies content equality, not logical identity or ownership. Identical content can belong to separate skills/accounts.
- Canonical Framework skills remain shared system objects. A synchronized local customization becomes an account-private fork, with its exact upstream source recorded. Identical system content needs no redundant personal copy.
- Saving a customized skill to an account does not automatically replace it in every project or change account defaults. Projects stay pinned until updated. A project-specific variation may remain local; promoting it to the account is explicit through sync. Reusing an existing personal skill versus creating another variation is an explicit identity decision, not a filename heuristic.

## Agreed requirement: preserve the fork graph

Erik approved preserving explicit parent → child lineage. A fork is a durable edge between two logical skills, anchored to the exact parent version used to create the child. Renaming either skill, publishing later versions, or forking a child again does not change that edge.

- Store a stable parent skill reference and the source parent version reference. The existing forked_from_id points to a source SkillVersion; evolve or expose it with unambiguous names rather than introducing unrelated lineage data.
- Support multiple children of one parent and chains such as Framework skill → account customization → another customization. Direct parents and ancestors must remain navigable; root origin can be derived.
- Present a quiet “Forked from …, version …” link on the child. Offer descendant links on the parent only where the viewer may access those descendants. A public parent must not reveal private forks, their owners, or their existence through counts.
- Keep version parent/base relationships separate from fork relationships: a new version updates an existing skill, while a fork creates a new skill linked to its origin. Record upstream update/merge provenance without rewriting the original fork edge.
- Preserve ancestry when an upstream item is retired. Use retained provenance/tombstone metadata rather than cascading deletion through the lineage graph. Visibility changes must continue to control what source content and identifying metadata a viewer can see.
- Enforce acyclic lineage: no self-parenting or reparenting a skill under its descendant. Assign fork edges at creation and treat them as immutable.

The first implementation preserves exact source-version fork edges and prevents reparenting.

## Agreed canonical versioning and import behavior

Canonical Framework skills must ship stable identity and explicit versions. An edited tracked canonical skill becomes a personal fork anchored to that exact source version. An untracked, unversioned skill imported by a user becomes their own private skill at version 1, with no inferred parent. A matching name never establishes ancestry. Existing local provenance still identifies a tracked Framework skill even if SKILL.md itself has no version field.

## Version the entire skill directory

Each version is an atomic snapshot containing SKILL.md plus scripts, references, templates, and other declared companion files. Store a deterministic manifest of normalized relative paths, SHA-256 hashes, sizes, and necessary file modes. Hash exact file bytes and a canonically encoded, path-sorted manifest; all three clients use shared cross-language test vectors. Do not hash archive bytes or timestamps.

File paths identify entries within a snapshot. Skill IDs identify bundles across versions. Renaming a skill folder or title preserves its skill ID. A companion-file rename can be represented as a removal/addition in the next manifest; per-file UUIDs are unnecessary initially. Hashes can assist rename detection, but ambiguous copies must not establish identity automatically.

Use the skill directory as the boundary. Do not follow references into the parent project or external symlinks. Generated caches, VCS metadata, credentials, and files outside the declared bundle are excluded. Deletions are recorded only for previously tracked files and reviewed as part of the sync diff. Explicit tombstones distinguish deletion from an unavailable/offline path.

Language variants require separate payload provenance: an R edit updates the R/default variant, while a Python edit updates the Python variant. Neither upload replaces the other variant. The resulting Cloud version remains a complete snapshot.

## Local synchronization state

Track managed skills in project metadata outside SKILL.md frontmatter, for example a versioned .framework/skills.lock.json plus a local ignored state/cache. Proposed fields: server, skill ID, selected language/variant, pinned/base version, exact source package version when applicable, base manifest/hash, installed paths, and content needed for a three-way comparison. Keep credentials in the existing credential store.

Multiple assistant folders can be installations of the same skill. Their paths are aliases for that logical skill, not independent account objects. If copies diverge, report the conflict; do not choose by modification time or upload competing versions silently. Independently copied untracked directories require adoption/import, even when their names match.

## Reconciliation

Compare local content and current account head against the exact last synchronized base:

| Local versus base | Cloud versus base | Action |
| --- | --- | --- |
| Unchanged | Unchanged | No-op |
| Changed | Unchanged | Upload a new private account version |
| Unchanged | Changed | Download and atomically install the new version |
| Changed | Changed to identical content | Advance synchronization state without a duplicate version |
| Changed | Changed differently | Three-way reconciliation; leave originals intact on conflict |

Compare upstream Framework updates separately. A new package release must never cause an unchanged older installation to be mistaken for a personal customization. Customized skills can accept reviewed upstream updates using the recorded upstream base.

Pushes include the expected head/base version and an idempotency key. The server locks the parent skill, checks ownership and head atomically, creates a version only for changed content, and returns the authoritative version/hash. Stale writes return 409 with enough metadata to reconcile. A timeout retry must not create another version. Downloads stage files and atomically replace managed content only after verification; state advances only after successful installation.

Initially auto-sync handles clean one-sided changes and reports conflicts. Add three-way text merging with reviewed previews after the base protocol is reliable. Binary conflicts, edit/delete conflicts, and competing assistant copies always need resolution. Do not use last-write-wins or timestamps to settle conflicts. Do not leave merge markers in active agent instructions.

Each successful save/sync is a version, not each keystroke. Diff, restore, and source/client metadata belong in the account UI. Restoring creates a new head; old snapshots remain immutable. Skill history should have an explicit retention policy independent of the existing capped settings history.

## User experience and delivery

Proposed command family: fw sync status, fw sync --dry-run, fw sync. Exact names remain to be checked against current CLI commands. R/Python expose equivalent project-skill sync entry points and machine-readable results under one protocol. Scaffolding records provenance immediately. No login and no network remain valid for local development.

1. Specify IDs, manifests, hashes, provenance, scopes, response/error contracts, and cross-language fixtures. Protect existing update commands from overwriting local edits.
2. Extend existing Cloud skill/version models and authenticated APIs for conditional account writes, complete bundles, history, and idempotent retries. Project pull tokens remain read-only.
3. Implement local base tracking and status/dry-run in R, Python, and CLI, including assistant aliases and package-version provenance.
4. Implement safe push/pull, atomic installation, offline behavior, and explicit conflict resolution. Test web-to-local, local-to-web, multiple machines, renamed paths, added/deleted files, interrupted operations, and language variants.
5. Add history/diffs/restore in Cloud, then optional reviewed three-way text merging and upstream update integration.

The same identity/version/bundle protocol can later cover structures, tools, package sets, and skill sets. Settings should keep their own revision protocol. Skill-set and blueprint versions pin skill versions so past project configurations remain reproducible.
