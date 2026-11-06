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

Claude Code and Codex are the easiest to set up. They run on your computer,
so they can use Framework Cloud's tools and run the commands that go with
them, such as uploading a large file. Claude and ChatGPT also work, with a few
more steps.

## Plugin

The Framework Cloud plugin adds the server to Claude, Claude Code, ChatGPT,
and Codex. Its marketplace is `frameworktools/plugins`. The plugin connects to
framework.pub only. On a self-hosted server, use the manual setup below.

- **Claude** (desktop app or claude.ai): Customize, then Plugins, then Add
  and Add marketplace. Enter `frameworktools/plugins`. Under Discover, choose
  Framework Cloud and select Add. It then works in chats and in Claude Code
  inside the desktop app. Add `framework.pub` to Claude's allowed websites, as
  described under Manual setup.
- **ChatGPT** (desktop app): Settings, then Plugins, then Add and Add a
  marketplace. Enter `frameworktools/plugins`, then install Framework Cloud.
- **Claude Code CLI:** run
  `claude plugin marketplace add frameworktools/plugins` and
  `claude plugin install framework-cloud@framework`, then `/mcp` in Claude
  Code to sign in.
- **Codex CLI:** run `codex plugin marketplace add frameworktools/plugins`,
  `codex plugin add framework-cloud@framework`, and
  `codex mcp login framework`.

## Manual setup

- **Claude** (desktop app or claude.ai): Customize, then Connectors, then Add
  and Custom connector.
  Choose Sign in now and Register automatically. Claude detects both. The
  connector also works in Claude Code when you sign in to it with the same
  Claude account.
- **Claude allowed websites:** with the plugin or the connector, open Settings,
  then Capabilities, and add `framework.pub` exactly as shown to the websites
  Claude may reach.
  Claude runs commands in a cloud sandbox, and without this entry the sandbox
  cannot upload files. Start a new chat after the change.
- **ChatGPT:** in the desktop app, open Settings, then Plugins, then Add and
  Add MCP server. Name it framework, choose Streamable HTTP (not STDIO), paste
  the address as the URL, and leave the rest empty. Save, then select
  Authenticate next to framework under MCPs. On chatgpt.com, use Settings,
  then Apps and connectors, then Create; custom connectors need developer mode
  on some plans.
- **Claude Code CLI:**
  `claude mcp add --transport http framework https://framework.pub/mcp`, then
  `/mcp` in Claude Code to sign in.
- **Codex CLI:** `codex mcp add framework --url https://framework.pub/mcp`,
  then `codex mcp login framework`.

Setup steps for each app are under AI Agents, Connect your agent. The app opens
Framework Cloud in a browser. Sign in, choose the app's access,
and select Connect.

## Access

| Access | Allowed actions |
|---|---|
| Full access (recommended) | Read everything. Create, rename, and delete projects. Design and save blueprints. Publish and delete artifacts. Create, edit, publish, and delete reports. Create, edit, and delete your own skills. |
| Read only | Read projects, artifacts, reports, skills, and blueprints. |

Anything an agent deletes goes to the Trash, in the sidebar, where you can
restore it. A deleted project's artifacts, reports, collections, and tokens
stop working until it is restored. Deleted artifacts keep their files, which
count toward storage until you delete them forever in the Trash. Agents cannot
delete built-in skills; an agent makes your own copy of one instead.

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

## Blueprints

An agent can design a blueprint from a description, such as "R with Quarto and
renv, using the privacy-sensitive layout." It reads the parts there are, shows
you the files and folders a project would get, and saves the blueprint once you
agree. New blueprints start private. Agents can change only your own
blueprints; to adapt a built-in one, an agent saves a copy.

## Linked projects

Any artifact can be linked to one or more projects, from the Settings tab on its
page or by an agent. A linked project lists the artifact and can use it in its
reports and collections. The artifact keeps its own project, or none.

What the app reads, including private projects and drafts, goes to the company
that runs it.

## Connected agents

Under AI Agents, Connect your agent has setup steps for each app: Claude,
ChatGPT, Claude Code CLI, and Codex CLI. After an agent connects, the page is
named Connected agents.

Permissions sets the access the approval screen selects for a new agent. It
also lists approved apps. A web app such as Claude or ChatGPT asks for
approval once, then reconnects with the same access. Change its access or
forget it there. Apps on your computer, such as Claude Code and Codex, always
ask.

Connections lists each approval and its access. Select Disconnect to end an
agent's access. Its tokens stop working at once. Removing a connector in Claude
or ChatGPT does not end the connection here. A connection unused for 30 days
ends on its own. To change an agent's access, disconnect it and connect it
again.

History lists each action an agent took, with the project and item it named
and the result. Request contents are not stored. History is kept for 90 days.
