# AI apps

Claude, ChatGPT, Claude Code, Codex, and other MCP-compatible AI apps can connect to your Framework Cloud account. Connected agents can inspect projects, blueprints, artifacts, skills, and reports. Write permissions allow agents to create blueprints, publish files, and assemble reports.

## Server address

MCP clients connect to your server URL followed by `/mcp`:

```text
https://framework.pub/mcp
```

On a self-hosted server, substitute your server's base URL.

Local coding agents such as Claude Code and Codex can pair MCP calls with local project files and shell commands, including `curl` uploads for larger artifacts. The Claude desktop app includes Claude Code and the ChatGPT desktop app includes Codex.

The `framework-cloud` plugin from `frameworktools/plugins` bundles agent skills for publishing, reports, and blueprints alongside the MCP configuration for framework.pub. On a self-hosted server, register the `/mcp` endpoint directly without the plugin.

During connection setup, your browser opens Framework Cloud to sign in, choose an access level, and select **Connect**. In the Framework Cloud sidebar, **Connect your agent** (shown as **Connected agents** once an agent is active) displays step-by-step instructions with your server URL prefilled.

## Claude

In the Claude desktop app:

1. **Connect the MCP server.** Open **Customize > Connectors**, select **Add > Custom connector**, and paste `https://framework.pub/mcp`. Select **Sign in now** and **Register automatically**, leaving **Request headers** blank.
2. **Configure the Framework plugin.** Open **Customize > Plugins**, select **Add > Add marketplace > Add from a repository**, and enter `frameworktools/plugins`. Under **Discover**, install **Framework Cloud**. Select **Manage** next to **Framework Cloud**, open **Connectors**, and select **Connect** if `framework` is disconnected.
3. **Open Claude Code.** Select `</>` at the top of the Claude desktop window.

From the command line:

```sh
claude plugin marketplace add frameworktools/plugins
claude plugin install framework-cloud@framework
```

To register the MCP server without the plugin, run `claude mcp add --transport http framework https://framework.pub/mcp`. Then start `claude`, run `/mcp`, choose `framework`, and complete browser authentication.

## ChatGPT

In the ChatGPT desktop app:

1. **Connect the MCP server.** Open **Settings > Plugins > MCPs** and select **Add**. Name the server `framework`, choose **Streamable HTTP**, paste `https://framework.pub/mcp` as the URL, leave optional fields blank, and save. When not installing the plugin, select **Authenticate** next to `framework`.
2. **Configure the Framework plugin.** On **Settings > Plugins**, select **Add** to add a marketplace, enter `frameworktools/plugins`, and install **Framework Cloud**. ChatGPT prompts you to sign in on first use.
3. **Open Codex.** Open the ChatGPT menu at the top left and select **Codex**.

From the command line:

```sh
codex plugin marketplace add frameworktools/plugins
codex plugin add framework-cloud@framework
```

To register the MCP server without the plugin, run `codex mcp add framework --url https://framework.pub/mcp` and authenticate with `codex mcp login framework`.

## Access

| Access | Allowed actions |
|---|---|
| Full access (recommended) | Read account items. Create, rename, and delete projects. Design and save blueprints. Publish and delete artifacts. Create, edit, publish, and delete reports. Create, edit, and delete personal skills. |
| Read only | Read projects, artifacts, reports, skills, and blueprints. |

Any content an agent reads is sent to the AI provider operating that agent. Readable content includes private projects, unpublished drafts, and artifact data.

Items deleted by an agent move to **Trash** in the sidebar, where you can restore them for 30 days. After that, they are deleted permanently. Self-hosted servers set this period with `FW_TRASH_DAYS`. Deleting a project disables its artifacts, reports, collections, and tokens until restored. Trashed artifact files continue counting toward storage quota until permanently deleted. Agents cannot modify or delete built-in skills. Editing a built-in skill creates a private copy in your account.

Connected agents access your entire account and never execute code on Framework Cloud.

## File publishing

With **Full access**, an agent can publish local files as artifacts: HTML notebooks, PNG or SVG figures, CSV tables, PDFs, `framework.artifact/v1` JSON summaries, or `.zip` HTML bundles with an entrypoint path. Agent-published artifacts start **private** and record the publishing agent on each version.

Small files upload inline through MCP tool calls. For larger files up to 50 MB, the agent requests a single-use upload URL valid for 30 minutes and uploads the file with `curl`. CLI-capable agents such as Claude Code and Codex run the `curl` upload directly, while chat-only apps provide the command for you to run.

Artifacts can be published with or without a project. Unassigned artifacts appear under artifacts with no project and can be embedded in account-level collections and reports.

Package commands (`publish()`, `fw_publish`, and `fw publish`) enforce private-folder checks against `settings.yml` before uploading. Direct agent file uploads bypass local project folder checks, so prefer package publishing inside sensitive projects. See [Publishing artifacts](publishing.md).

## Blueprints

Agents can compose a blueprint from natural-language requirements, preview the resulting directory tree and resolved pieces, and save a **private** blueprint after you confirm. Agents can edit only your personal blueprints and copy built-in blueprints before making changes.

## Linked projects

An artifact can be linked to additional projects from its **Settings** tab or through an agent tool call. Linked projects list the artifact on their **Artifacts** tab and can include it in project reports and collections while preserving the artifact's original owning project.

## Connected agents

Opening **Connect your agent** or **Connected agents** in the sidebar provides four management pages under **AI agents**:

- **Setup.** Displays connection instructions and server URLs for Claude, Claude Code, ChatGPT, and Codex.
- **Permissions.** Sets the default access preselected on the OAuth approval screen and lists remembered web apps. Changing a remembered web app's access level updates open sessions immediately, while local CLI clients such as Claude Code and Codex prompt on each new login.
- **Connections.** Lists active agent approvals and access levels. Selecting **Disconnect** revokes that agent's tokens immediately, whereas removing a connector inside Claude or ChatGPT alone leaves the server token active until it expires after 30 days of inactivity. To change permissions for Claude Code or Codex, disconnect the session here and sign in again.
- **History.** Logs agent tool actions with timestamps, target projects, target items, and outcomes for 90 days without storing request payloads.
