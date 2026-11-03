# Project operating context

## Sharing previews with Erik

When sharing a local project preview, rendered document, or worktree, use its actual public HTTPS tunnel URL with the relevant path. Keep external production and repository links as appropriate. Localhost, 127.0.0.1, `.test` hosts, and filesystem paths do not provide remote access.

- On the Mac, run `tunnels` (or `~/System/config/bin/tunnels` if it is not on PATH) to discover the exact public URL and matching project/worktree. `tunnels -a` includes other Herd proxies; `tunnels open <name>` opens a match. Share the discovered `https://…pequod.dev` URL; do not guess its hostname.
- Mac Herd sites automatically use `https://<site>.pequod.dev`. Sub-sites `<sub>.<site>.test` map to `https://<sub>--<site>.pequod.dev`. Real hostname collisions may use `<site>-mac`. Worktrees require their own registered Herd site. The Mac tunnel runs at login; do not create per-site DNS or ad hoc tunnels.
- If a site is missing, use the project's registered T3 Workspace setup script and existing projectctl/bootstrap flow. For a Herd worktree, run `herd link <unique-site-name>` inside that worktree. `projectctl workspace-link` is for container/Caddy workflows and rejects Herd projects.
- Keep the app/backend running. Build frontend assets with the project's build command (`npm run build` here) for remote previews; a Vite server pointing to localhost will not load remotely. Apps must trust loopback proxies so HTTPS and public-host URLs are correct.
- Verify the local origin and then public routing before claiming a preview works. Cloudflare Access redirects are expected but do not prove that the origin works. Access requires Erik's GitHub login or an emailed PIN at erik@pequod.sh or edbwestlund@gmail.com. Do not disable Access or print tunnel credentials.

## Quarto previews

- Use `quarto-sites list` to discover project domains and `quarto-sites up` to reconcile after `projects.yml` changes.
- Start live previews with `quarto-preview <path/to/doc.qmd>` and check readiness with `quarto-preview status`. Share `https://<discovered-project-domain>.pequod.dev/preview`. Rendered outputs use the public site's normal paths. Heavy first renders may continue in the background.
- Cloudflare Access supplies authentication; `quarto-sites auth` is unnecessary for these tunnels.

## Tunnel troubleshooting and VM worktrees

- Mac service inspection: `launchctl print gui/$(id -u)/com.erik.system.preview-caddy` and the corresponding `com.erik.system.preview-tunnel`. Logs: `~/.config/preview-route/caddy.log` and `cloudflared.log`.
- Shared infrastructure, configuration, and package fixes belong in `~/System` through Ansible. The desktop role (`mac-sys --tags desktop`, desktop-scoped) reconciles the tunnel and LaunchAgents. Follow `~/System/README.md`, `config/bin/tunnels`, and `infra/directions/remote-code.md`.
- On the remote-code VM, use the registered workspace setup script and `projectctl workspace-link <alias> <domain> <workspace-path>` for Caddy routes. Discover exact hosts with `~/.local/bin/preview-route list`; Framework Cloud hosts are typically `https://fc-<worktree>.pequod.dev`.
- `preview-route add <host>` manages explicit VM DNS when needed. Preserve other tunnels' ownership. VM services are `caddy` and `remote-code-previews.service`; setup and reconciliation belong in the infra repository's remote-code previews role, documented in `directions/remote-code.md`.

This preference does not authorize unrelated implementation, deployment, or infrastructure changes. Do not commit or push solely to save it. Coordinate shared-file edits with the parent when working as a subagent, and preserve ongoing work.

Main checkout preview discovered with `tunnels` on 2026-11-02: https://framework-cloud.pequod.dev (maps to this checkout). Rediscover when preparing another checkout or worktree.

## Design

Load the `cloud-design` skill (`.claude/skills/cloud-design/SKILL.md`) before building or restyling any page or component. It records the academic.page / Flux design language this app follows and the shared Vue components that implement it.

## Prose

Load `.claude/skills/cloud-prose/SKILL.md` before writing or editing copy. Use short, terse sentences. Headings and labels use descriptive nouns or concise noun phrases, such as “Artifact storage” and “Server administration.” Avoid conversational headings such as “Where published notebooks go” and “What you manage.” Buttons use direct action verbs.
