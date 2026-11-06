<?php

namespace App\Mcp;

use App\Models\McpConnection;
use Laravel\Mcp\Server;

/**
 * The hosted MCP server at /mcp. Each request belongs to one connection
 * (ResolveMcpConnection), which fixes the account and the access level, so
 * the tool list is built per request: a Read only connection sees only reads.
 */
class FrameworkServer extends Server
{
    protected string $name = 'Framework Cloud';

    protected string $version = '1.0.0';

    // Every tool on one page: clients that don't follow tools/list cursors would miss the rest
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

    protected string $instructions = <<<'MARKDOWN'
Tools for reading one Framework Cloud account's projects and published results, and for writing reports from them. account_show names the account.

- Published artifacts are figures, tables, models, data, and notebooks. An artifact belongs to one project or to none, and can be linked to other projects, which list it and may use it in reports. artifacts_list lists them; artifacts_show returns a version's content when it is text, such as a model's JSON or a CSV table.
- A report is one page of Markdown text with published artifacts placed between the paragraphs. Build it with reports_create, revise it with reports_update, which replaces the whole draft, and publish it with reports_publish. Each publish adds a version at the same stable link.
- Write report text from the published results. Take estimates, counts, and names from artifacts_show; never invent numbers.
- Reports start private. Ask before making one unlisted or public. A public report may only show public artifacts.
- Publish only when the user asks. With "Read only" access you can read but not create or change reports.
- Publishing reports needs a paid plan. A 402 error means the account has none; tell the user.
- To publish a file: for anything larger than a few hundred KB, or a file on disk, call artifacts_upload and run the curl command it returns (or give it to the user). Never paste a large file into a call. artifacts_publish takes small files inline. HTML publishes as a notebook, PNG or SVG as a figure, CSV as a table; a rendered Quarto site goes as a zip with its entrypoint. A project is optional. New artifacts start private.
- When you can run commands inside a Framework project, publishing with fw publish or the R, Python, or Stata package is better still: they check the project's private folders first.
- To use an artifact from elsewhere in a report, link it to the report's project with artifacts_link first.
- Projects: projects_create makes one from the account's defaults or a blueprint; its files are made on the user's computer or in GitHub. projects_update renames one or changes its description. Create or rename only when the user asks.
- Blueprints are reusable project setups. To design one: read the parts with blueprints_options (and blueprints_show for a built-in to start from), propose choices, show the user the files with blueprints_preview, and create it with blueprints_create once they agree. Then projects_create can start a project from it.
- Skills are agent instructions (SKILL.md). skills_show reads one; skills_create and skills_update change the account's own. Built-in skills (framework/...) can't be changed; create your own from their text instead. Read a skill before updating it, and pass its version as expected_version.
- projects_delete, artifacts_delete, reports_delete, and skills_delete move things to the Trash, where the user can restore them. Delete only when the user asks, and say what you will delete first. Built-in skills can't be deleted.
- These tools cannot run code.
- Every tool accepts dry_run=true to preview the request without sending it.
- Errors come back as JSON {error: {status, code, message}}, with errors for validation failures.
MARKDOWN;

    protected function boot(): void
    {
        $connection = request()->attributes->get('mcp_connection');

        if (! $connection instanceof McpConnection) {
            return;
        }

        foreach (ToolCatalog::tools() as $name => $tool) {
            if (ToolCatalog::allows($connection->access, $tool['level'])) {
                $this->tools[] = new ApiTool($name, $tool, $connection);
            }
        }
    }
}
