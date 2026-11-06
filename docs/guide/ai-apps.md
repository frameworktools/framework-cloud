# AI apps

Claude, ChatGPT, and other AI apps can connect to your Framework Cloud account.
A connected app reads your projects, published artifacts, and reports. It can
also write reports from your published results.

## Server address

Add Framework Cloud as a connector, or custom MCP server, in the app. Use your
server's address followed by `/mcp`:

```
https://framework.pub/mcp
```

On a self-hosted server, use that server's address instead.

- **Claude:** Customize, then Connectors, then Add and Custom connector.
  Choose Sign in now and Register automatically. Claude detects both.
- **ChatGPT:** Settings, then Apps and connectors, then Create. Custom
  connectors need developer mode on some plans.
- **Claude Code:** `claude mcp add --transport http framework https://framework.pub/mcp`

Setup steps for each app are under AI Agents, Connect your agent. The app opens
Framework Cloud in a browser. Sign in, choose the app's access,
and select Connect.

## Access

| Access | Allowed actions |
|---|---|
| Full access (recommended) | Read projects, artifacts, and reports. Publish saved files as artifacts. Create, edit, and publish reports. |
| Read only | Read projects, artifacts, and reports. |

A connected app works across all your projects. It cannot run code.

## Publishing files

With Full access, an agent can publish a saved file: an HTML file as a notebook,
a PNG or SVG as a figure, a CSV as a table, a PDF, or a model's
`framework.artifact/v1` JSON. A zip of rendered output with its HTML entrypoint,
such as a Quarto site, publishes as one artifact. New artifacts start private.
Each version records which agent published it.

Small files go straight into the agent's call. For a larger file, up to 50 MB,
the agent asks for an upload link and sends the file with `curl`. An agent that
can run commands, such as Claude Code or Codex, runs the command itself. Others
give you the command to run. Each link works once and expires after 30 minutes.

A project is optional. Artifacts published without one are listed under
Artifacts with no project.

The R, Python, and Stata packages and `fw publish` check the project's private
folders before publishing. An agent publishing a file directly cannot. Use the
packages for results from a project. See [Publishing artifacts](publishing.md).

## Linked projects

Any artifact can be linked to one or more projects, from the Settings tab on its
page or by an agent. A linked project lists the artifact and can use it in its
reports and collections. The artifact keeps its own project, or none.

What the app reads, including private projects and drafts, goes to the company
that runs it.

## Connected agents

Under AI Agents, Connect your agent has the server address and setup steps for
each app. After an agent connects, the page is named Connected agents and lists
each agent with its access. Select Disconnect to end an agent's access. Its
tokens stop working at once. To change an agent's access, disconnect it and
connect it again.

The History tab lists each action an agent took, with the project and item it
named and the result. Request contents are not stored. History is kept for 90
days.
