# AI apps

Claude, ChatGPT, and other AI apps can connect to your Framework Cloud account.
A connected app reads your projects, published artifacts, and reports. It can
also write reports from your published results.

## Server address

Every app connects to the same address, your server followed by `/mcp`:

```
https://framework.pub/mcp
```

On a self-hosted server, use that server's address instead.

Claude Code and Codex work best. They run on your computer, so they can work
with your project's files and run the commands that go with Framework Cloud's
tools, such as uploading a large file. The Claude desktop app includes Claude
Code, and the ChatGPT desktop app includes Codex.

The Framework Cloud plugin, from the marketplace `frameworktools/plugins`, adds
skills for publishing, reports, and blueprints. It connects to framework.pub
only. On a self-hosted server, connect the MCP server alone.

When an app connects, Framework Cloud opens in a browser. Sign in, choose the
app's access, and select Connect. AI Agents, Setup in Framework Cloud has these
steps for each app, with your server's address filled in.

## Claude

In the Claude desktop app:

1. **Connect the MCP server.** Open Customize, then Connectors. Select Add,
   then Custom connector, and paste the server address. Choose Sign in now and
   Register automatically, and leave Request headers empty.
2. **Add the plugin.** Open Customize, then Plugins. Select Add, then Add
   marketplace, then Add from a repository, and enter
   `frameworktools/plugins`. Under Discover, add Framework Cloud. Then select
   Manage next to it, open Connectors, and if framework is not connected,
   select Connect, then Continue.
3. **Use it in Claude Code.** Select `</>` at the top of the Claude desktop app
   to switch to Claude Code.

From the command line:

```
claude plugin marketplace add frameworktools/plugins
claude plugin install framework-cloud@framework
```

Or add the server alone with
`claude mcp add --transport http framework https://framework.pub/mcp`. Then run
`claude`, type `/mcp`, choose framework, and authenticate.

## ChatGPT

In the ChatGPT desktop app:

1. **Connect the MCP server.** Open Settings, then Plugins, and choose the
   MCPs tab. Select Add to add a custom MCP server. Name it framework, select
   Streamable HTTP, paste the server address as the URL, leave the rest empty,
   and save it. Without the plugin, select Authenticate next to framework.
2. **Add the plugin.** On the same Plugins page, select Add to add a
   marketplace, and enter `frameworktools/plugins`. Search for Framework and
   install Framework Cloud. It asks you to sign in the first time you use it.
3. **Use it in Codex.** Open the ChatGPT menu at the top left and choose
   Codex.

From the command line:

```
codex plugin marketplace add frameworktools/plugins
codex plugin add framework-cloud@framework
```

Or add the server alone with
`codex mcp add framework --url https://framework.pub/mcp`. Then run
`codex mcp login framework` to sign in.

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
Artifacts with no project. Reports and collections can also have no project,
and then they can use any artifact in your account.

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

AI Agents in Framework Cloud has four pages.

Setup has the steps above for Claude and ChatGPT.

Permissions sets the access the approval screen selects for a new agent. It
also lists approved apps. A web app such as Claude or ChatGPT asks for
approval once, then reconnects with the same access. Change its access or
forget it there. A change applies right away, including to the app's open
connections. Apps on your computer, such as Claude Code and Codex, always ask.

Connections lists each approval and its access. Select Disconnect to end an
agent's access. Its tokens stop working at once. Removing a connector in Claude
or ChatGPT does not end the connection here. A connection unused for 30 days
ends on its own. To change the access of Claude Code or Codex, disconnect it
and connect it again.

History lists each action an agent took, with the project and item it named
and the result. Request contents are not stored. History is kept for 90 days.
