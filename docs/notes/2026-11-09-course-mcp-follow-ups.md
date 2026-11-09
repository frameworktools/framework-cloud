# Course build through the MCP: follow-ups

Date: 2026-11-09. Work for the Yale AI stats course (Plan Course Materials thread).

## Done

- framework.pub has a queue worker, a Forge background process. Deploys restart it (infra e1f5818 and later).
- MCP tools: projects_scaffold, projects_github_link, projects_files_list, projects_files_write, projects_files_upload. projects_show reports `scaffold`. projects_update takes `active` and `public_download`.
- The five yale-ai-stats projects are linked to their erikwestlund-teaching repos. Public download is on for 01.
- A user's own blueprint gets only the skill and package sets it names. The packages' default Framework skills are removed unless a chosen set brings them.
- Blueprints accept `core_files: false` and `settings_layout: flat` in extra_yaml.
- framework d8b4182, then 237167f: .gitkeep in empty folders, an .Rproj unless ide is none, and skill descriptions in AGENTS.md. core_files false leaves out only .env and scaffold.R. GitHub scaffolds pin 237167f through the vault's GITHUB_FRAMEWORK_SHA, and so does the zip scaffolder image.
- File writes with identical content make no commit (`unchanged: true`).
- Sensitive projects with a public repository may have a public download. The zip never includes private/ folders.
- Present (a QR code and framework.pub/go/{code}) appears on artifacts, reports, collections, and public or unlisted blueprints, which now have pages at /b/{key}. Blueprints start private.
- Projects have an active flag, with Active and All tabs.

## Steps left

1. Reconnect the claude.ai "framework" connector, or start a new chat, so sessions see the new tools. Production serves them already.
2. Done: Erik deleted the test repos fc-scaffold-smoke-test, -2, -3 and yale-ai-stats-e2e-test on 2026-11-09.

Done since: the planner tested the file tools end to end (fresh project, write, zip upload, clone, here::here()); public download is on for 01–03; 04 keeps its GitHub link, because its data sits in private/; and the token `course-mcp-fix-verify` is revoked.

## Open

- A per-project "include private folders (public data)" option for public downloads, for teaching projects like 04. Low priority.

- `fw new --from` registers the project with its slug as the name and no description (framework-cli).
- academic.page QR presenter and /go links: briefed to the pequod-4b session; PR pending Erik's review.

- AGENTS.md always includes the Framework Environment and scaffold() sections. A plain option needs work in the R package.
- `sandbox.failIfUnavailable: true` in sensitive projects. It is a deliberate fail-closed setting, but it may block Claude Code on student machines without sandbox support.
- framework-cli's dist snapshot calls /api/v2/blueprints. A build from HEAD works.
- The cloud's Stata conformance fixtures lag fw 0.4.6. The cloud keeps its own copies until the preview catches up.
