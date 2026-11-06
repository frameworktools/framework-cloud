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
| Full access (recommended) | Read projects, artifacts, and reports. Create, edit, and publish reports, and change who can read them. |
| Read only | Read projects, artifacts, and reports. |

A connected app works across all your projects. It cannot run code or publish
new artifacts. Publish results from the project on your computer first. See
[Publishing artifacts](publishing.md).

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
