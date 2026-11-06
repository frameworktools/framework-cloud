<?php

namespace App\Mcp;

use App\Models\McpConnection;

/**
 * The API operations offered as MCP tools, and the access level each needs.
 *
 * A tool runs as a request to /api/v1 (ApiDispatcher). Path parameters, the
 * query, and the JSON body become its arguments. Tools above a connection's
 * level are not listed at all; AccessPolicy checks each call as well.
 */
class ToolCatalog
{
    public const READ = 'read';

    public const FULL = 'full';

    /** @return array<string, array<string, mixed>> */
    public static function tools(): array
    {
        $project = ['slug' => ['type' => 'string', 'description' => 'The project slug, from projects_list.']];
        $report = ['report' => ['type' => 'string', 'description' => 'The report slug, from reports_list.']];
        $language = ['language' => ['type' => 'string', 'enum' => ['r', 'python', 'stata'], 'description' => 'Keep only items for this language.']];

        return [
            'account_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/me',
                'title' => 'Show the account',
                'description' => 'The signed-in account: name and email.',
            ],
            'projects_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/projects',
                'title' => 'List projects',
                'description' => 'The account\'s projects, newest first, with slug, name, type, and language.',
            ],
            'projects_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/projects/{slug}', 'path_params' => $project,
                'title' => 'Show a project',
                'description' => 'One project: its blueprint, its GitHub repository and Pages site, and when it was created.',
            ],
            'artifacts_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/projects/{slug}/artifacts', 'path_params' => $project,
                'title' => 'List published artifacts',
                'description' => 'A project\'s published figures, tables, models, data, and notebooks, with slug, kind, visibility, link, and latest version.',
            ],
            'artifacts_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/projects/{slug}/artifacts/{artifact}',
                'path_params' => $project + ['artifact' => ['type' => 'string', 'description' => 'The artifact slug, from artifacts_list.']],
                'query' => ['version_number' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A version to read. Defaults to the latest.']],
                'title' => 'Show a published artifact',
                'description' => 'One artifact and its versions. For text content (a model\'s JSON, a CSV table, Markdown), content holds the version\'s content, up to 200 KB. Take estimates and counts from here, never invent them.',
            ],
            'reports_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/projects/{slug}/reports', 'path_params' => $project,
                'title' => 'List reports',
                'description' => 'A project\'s reports, with slug, visibility, link, and latest published version.',
            ],
            'reports_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/projects/{slug}/reports/{report}', 'path_params' => $project + $report,
                'title' => 'Show a report',
                'description' => 'One report: its draft blocks and its published versions.',
            ],
            'skills_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/skills', 'query' => $language,
                'title' => 'List skills',
                'description' => 'The Framework skills the account can use: agent instructions for working in Framework projects.',
            ],
            'blueprints_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/blueprints', 'query' => $language,
                'title' => 'List blueprints',
                'description' => 'The project blueprints the account can start from.',
            ],
            'reports_create' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/projects/{slug}/reports', 'path_params' => $project,
                'body' => self::reportBody(creating: true),
                'title' => 'Create a report',
                'description' => 'Create a report: an ordered page of Markdown text and published artifacts. It starts private and unpublished unless publish is true.',
            ],
            'reports_update' => [
                'level' => self::FULL, 'method' => 'PATCH', 'path' => '/projects/{slug}/reports/{report}', 'path_params' => $project + $report,
                'body' => self::reportBody(creating: false),
                'title' => 'Update a report draft',
                'description' => 'Change a report\'s draft. blocks replaces the whole draft, so send every block you want to keep. Published versions do not change until you publish.',
            ],
            'reports_publish' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/projects/{slug}/reports/{report}/publish', 'path_params' => $project + $report,
                'title' => 'Publish a report',
                'description' => 'Publish the report\'s draft as a new version at its stable link. Artifacts are pinned at their latest versions unless a block names one. Publish only when the user asks.',
            ],
        ];
    }

    /** May a connection at this access level use a tool at this level? */
    public static function allows(string $access, string $level): bool
    {
        return match ($level) {
            self::READ => true,
            self::FULL => $access === McpConnection::ACCESS_FULL,
            default => false,
        };
    }

    /** @return array{properties: array<string, mixed>, required: list<string>} */
    private static function reportBody(bool $creating): array
    {
        $block = [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['text', 'artifact']],
                'id' => ['type' => 'string', 'description' => 'A stable id for the block. Optional; give one to update the block later.'],
                'text' => ['type' => 'string', 'description' => 'For text blocks: Markdown. Raw HTML in Markdown is removed.'],
                'format' => ['type' => 'string', 'enum' => ['html'], 'description' => 'For text written as HTML. Only basic tags are kept.'],
                'artifact' => ['type' => 'string', 'description' => 'For artifact blocks: the published artifact\'s slug in this project.'],
                'version_number' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Pin an artifact version. Defaults to the latest when published.'],
                'caption' => ['type' => 'string'],
                'share_unlisted' => ['type' => 'boolean', 'description' => 'Show an unlisted artifact in an unlisted report.'],
            ],
            'required' => ['type'],
        ];

        return [
            'properties' => [
                'title' => ['type' => 'string', 'maxLength' => 200],
                'description' => ['type' => 'string'],
                'slug' => ['type' => 'string', 'description' => 'The link\'s slug. Generated from the title if left out.'],
                'visibility' => ['type' => 'string', 'enum' => ['private', 'unlisted', 'public'], 'description' => 'Who can read the published report. Ask the user before choosing public.'],
                'password' => ['type' => 'string', 'minLength' => 8],
                'remove_password' => ['type' => 'boolean'],
                'blocks' => ['type' => 'array', 'maxItems' => 100, 'items' => $block, 'description' => 'The page, in order.'],
                'markdown' => ['type' => 'string', 'description' => 'Instead of blocks: the whole page as one Markdown text block.'],
                'publish' => ['type' => 'boolean', 'description' => 'Publish right after saving.'],
            ],
            'required' => $creating ? ['title'] : [],
        ];
    }
}
