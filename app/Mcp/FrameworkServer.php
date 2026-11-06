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

    protected string $instructions = <<<'MARKDOWN'
Tools for reading one Framework Cloud account's projects and published results, and for writing reports from them. account_show names the account.

- A project's published artifacts are its figures, tables, models, data, and notebooks, published from R, Python, Stata, or the fw command line. artifacts_list lists them; artifacts_show returns a version's content when it is text, such as a model's JSON or a CSV table.
- A report is one page of Markdown text with published artifacts placed between the paragraphs. Build it with reports_create, revise it with reports_update, which replaces the whole draft, and publish it with reports_publish. Each publish adds a version at the same stable link.
- Write report text from the published results. Take estimates, counts, and names from artifacts_show; never invent numbers.
- Reports start private. Ask before making one unlisted or public. A public report may only show public artifacts.
- Publish only when the user asks. With "Read only" access you can read but not create or change reports.
- Publishing reports needs a paid plan. A 402 error means the account has none; tell the user.
- artifacts_publish publishes a saved file the user gives you: an HTML file as a notebook, a PNG as a figure, a CSV as a table, a PDF, or a zip of a rendered site with its entrypoint. New artifacts start private. When you can run commands in the project, publish with fw publish or the R, Python, or Stata package instead; they check the project's private folders.
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
