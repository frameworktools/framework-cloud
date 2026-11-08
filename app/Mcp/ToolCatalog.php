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
        $optionalProject = ['project' => ['type' => 'string', 'description' => 'A project slug, from projects_list. Optional.']];
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
            'projects_create' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/projects',
                'hide' => ['token'],
                'body' => ['properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100],
                    'description' => ['type' => 'string', 'maxLength' => 500],
                    'language' => ['type' => 'string', 'enum' => ['r', 'python', 'stata'], 'description' => 'Defaults to the account\'s default language.'],
                    'blueprint' => ['type' => 'string', 'description' => 'A blueprint key from blueprints_list. Defaults to the account\'s default blueprint.'],
                    'github' => ['type' => 'object', 'description' => 'Also create its GitHub repository. Needs the account\'s GitHub connection.', 'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'The GitHub user or organization.'],
                        'name' => ['type' => 'string', 'description' => 'The repository name. Defaults to the project slug.'],
                        'private' => ['type' => 'boolean'],
                    ], 'required' => ['owner']],
                ], 'required' => ['name']],
                'title' => 'Create a project',
                'description' => 'Create a Framework project from the account\'s defaults or a blueprint. Its files are made on the user\'s computer (framework::new_project, fw new) or in a GitHub repository with github. Create one only when the user asks.',
            ],
            'projects_update' => [
                'level' => self::FULL, 'method' => 'PATCH', 'path' => '/projects/{slug}', 'path_params' => $project,
                'body' => ['properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100],
                    'description' => ['type' => 'string', 'maxLength' => 500],
                ], 'required' => []],
                'title' => 'Update a project',
                'description' => 'Rename a project or change its description. Its slug and links stay the same.',
            ],
            'projects_delete' => [
                'level' => self::FULL, 'method' => 'DELETE', 'path' => '/projects/{slug}', 'path_params' => $project,
                'title' => 'Delete a project',
                'description' => 'Delete a project. Its artifacts, reports, collections, and tokens stop working until it is restored.'.' It goes to the Trash in Framework Cloud, where the user can restore it. Delete only when the user asks, and name what you will delete first.',
            ],
            'artifacts_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/artifacts', 'query' => $optionalProject,
                'title' => 'List published artifacts',
                'description' => 'Published figures, tables, models, data, and notebooks, with slug, kind, visibility, link, latest version, their project (null for none), and the projects they are linked to. Pass project for one project\'s own and linked artifacts; leave it out for the whole account.',
            ],
            'artifacts_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/artifacts/{artifact}',
                'path_params' => ['artifact' => ['type' => 'string', 'description' => 'The artifact slug, from artifacts_list.']],
                'query' => $optionalProject + ['version_number' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A version to read. Defaults to the latest.']],
                'title' => 'Show a published artifact',
                'description' => 'One artifact and its versions. For text content (a model\'s JSON, a CSV table, Markdown), content holds the version\'s content, up to 200 KB. Take estimates and counts from here, never invent them. Pass project when several projects have an artifact with this slug.',
            ],
            'artifacts_publish' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/artifacts',
                'rename' => ['artifact' => 'slug'],
                'body' => self::publishBody(),
                'title' => 'Publish an artifact',
                'description' => 'Publish a small saved file as an artifact, sent in the call: an HTML file as a notebook, a PNG or SVG as a figure, a CSV as a table, a PDF, Markdown, or a framework.artifact/v1 model JSON. Text goes in content, binary in content_base64. Use this only for files of a few hundred KB at most. For larger files, or a file on disk, use artifacts_upload instead. A project is optional. Publishing the same name again adds a version at the same link. New artifacts start private.',
            ],
            'artifacts_delete' => [
                'level' => self::FULL, 'method' => 'DELETE', 'path' => '/artifacts/{artifact}',
                'path_params' => ['artifact' => ['type' => 'string', 'description' => 'The artifact slug.']],
                'query' => $optionalProject,
                'title' => 'Delete an artifact',
                'description' => 'Delete an artifact with all its versions. Its links stop working, and reports and collections show it as unavailable. It goes to the Trash in Framework Cloud, with its files, where the user can restore it. Delete only when the user asks, and name what you will delete first. Pass project when several projects have an artifact with this slug.',
            ],
            'artifacts_upload' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/artifacts/uploads',
                'rename' => ['artifact' => 'slug'],
                'body' => self::uploadBody(),
                'title' => 'Get an upload link',
                'description' => 'For a file on disk or too large to send in a call, up to 50 MB: returns a one-time upload link and a curl command that publishes the file when run. If you can run commands, run it yourself; otherwise give it to the user. A rendered Quarto site goes as a zip with its entrypoint. A project is optional. The link expires in 30 minutes. New artifacts start private.',
            ],
            'artifacts_link' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/projects/{project}/linked-artifacts',
                'path_params' => ['project' => ['type' => 'string', 'description' => 'The project to list the artifact in.']],
                'body' => ['properties' => [
                    'artifact' => ['type' => 'string', 'description' => 'The artifact slug.'],
                    'from_project' => ['type' => 'string', 'description' => 'The artifact\'s own project. Leave out for an artifact with no project.'],
                ], 'required' => ['artifact']],
                'title' => 'Link an artifact to a project',
                'description' => 'List an artifact in another project, which may then use it in reports and collections. The artifact stays where it is; an artifact can be linked to several projects.',
            ],
            'artifacts_unlink' => [
                'level' => self::FULL, 'method' => 'DELETE', 'path' => '/projects/{project}/linked-artifacts/{artifact}',
                'path_params' => ['project' => ['type' => 'string'], 'artifact' => ['type' => 'string']],
                'query' => ['from_project' => ['type' => 'string', 'description' => 'The artifact\'s own project, when two linked artifacts share a slug.']],
                'title' => 'Unlink an artifact from a project',
                'description' => 'Stop listing a linked artifact in a project. The artifact itself is unchanged.',
            ],
            'reports_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/reports',
                'query' => ['project' => ['type' => 'string', 'description' => 'A project slug, from projects_list, to list only that project\'s reports.']],
                'title' => 'List reports',
                'description' => 'The account\'s reports, with slug, visibility, link, latest published version, and project: the slug to pass to the other report tools.',
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
            'skills_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/skills/{skill}',
                'path_params' => ['skill' => ['type' => 'string', 'description' => 'The skill key from skills_list, such as framework-reports, framework/framework-reports, or @owner/name.']],
                'query' => ['language' => ['type' => 'string', 'enum' => ['r', 'python', 'stata'], 'description' => 'The language version to read. Defaults to the skill\'s first.']],
                'title' => 'Show a skill',
                'description' => 'One skill\'s SKILL.md text, its version, and whether this account can change it.',
            ],
            'skills_create' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/skills',
                'body' => self::skillBody(),
                'title' => 'Create a skill',
                'description' => 'Create a skill from SKILL.md text: frontmatter with name: and description:, then the instructions. It starts private. To change a built-in skill, create your own from its text under a new name.',
            ],
            'skills_update' => [
                'level' => self::FULL, 'method' => 'PUT', 'path' => '/skills/{skill}',
                'path_params' => ['skill' => ['type' => 'string', 'description' => 'The skill key from skills_list, such as framework-reports, framework/framework-reports, or @owner/name.']],
                'body' => self::skillBody(),
                'title' => 'Update a skill',
                'description' => 'Save a new version of one of the account\'s own skills from its full SKILL.md text. Read it with skills_show first and pass its version as expected_version. Keep its name: in the frontmatter. Earlier versions stay in its history.',
            ],
            'skills_delete' => [
                'level' => self::FULL, 'method' => 'DELETE', 'path' => '/skills/{skill}',
                'path_params' => ['skill' => ['type' => 'string', 'description' => 'One of the account\'s own skills, by key.']],
                'title' => 'Delete a skill',
                'description' => 'Delete one of the account\'s own skills. Projects that use it keep their copy.'.' It goes to the Trash in Framework Cloud, where the user can restore it. Delete only when the user asks, and name what you will delete first.',
            ],
            'blueprints_options' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/blueprints/options',
                'title' => 'List blueprint parts',
                'description' => 'What a blueprint is built from: structures (folder layouts), tools (quarto, jupyter, renv, uv, targets, and others, with the languages they fit and what they require), package sets, skill sets, and skills, each with its key and a summary. Use the keys in blueprints_preview and blueprints_create.',
            ],
            'blueprints_show' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/blueprints/{blueprint}',
                'path_params' => ['blueprint' => ['type' => 'string', 'description' => 'The blueprint key from blueprints_list, such as framework/project or @owner/name.']],
                'title' => 'Show a blueprint',
                'description' => 'One blueprint\'s choices (structure, tools, package sets, skill sets, skills, settings, extra YAML, languages, and workflows), its version, and whether this account can change it. Start a new blueprint from these choices to adapt a built-in one.',
            ],
            'blueprints_preview' => [
                'level' => self::READ, 'method' => 'POST', 'path' => '/blueprints/preview', 'read_only' => true,
                'body' => self::blueprintBody(preview: true),
                'title' => 'Preview a blueprint',
                'description' => 'The files and folders a project from these blueprint choices would get, for one language and workflow, without saving anything. Use it to propose a blueprint and show the user before creating it. A problem with the choices comes back as problem.',
            ],
            'blueprints_create' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/blueprints',
                'body' => self::blueprintBody(),
                'title' => 'Create a blueprint',
                'description' => 'Save a blueprint from choices: a structure, tools, package sets, skill sets, skills, settings, and extra settings.yml YAML. Preview it with blueprints_preview and get the user\'s go-ahead first. It starts private. Projects can then start from it with projects_create.',
            ],
            'blueprints_update' => [
                'level' => self::FULL, 'method' => 'PUT', 'path' => '/blueprints/{blueprint}',
                'path_params' => ['blueprint' => ['type' => 'string', 'description' => 'The blueprint key from blueprints_list, such as framework/project or @owner/name.']],
                'body' => self::blueprintBody(),
                'title' => 'Update a blueprint',
                'description' => 'Save a new version of one of the account\'s own blueprints from the full choices (read them with blueprints_show, change what is needed, send them all). Projects already made from it keep the version they used.',
            ],
            'blueprints_list' => [
                'level' => self::READ, 'method' => 'GET', 'path' => '/blueprints', 'query' => $language,
                'title' => 'List blueprints',
                'description' => 'The project blueprints the account can start from.',
            ],
            'reports_create' => [
                'level' => self::FULL, 'method' => 'POST', 'path' => '/reports',
                'body' => self::reportBody(creating: true),
                'title' => 'Create a report',
                'description' => 'Create a report: an ordered page of Markdown text and published artifacts. Give a project slug to put it in that project, or leave project out: a report with no project can use any artifact in the account. It starts private and unpublished unless publish is true. The response\'s project is the slug for the other report tools.',
            ],
            'reports_update' => [
                'level' => self::FULL, 'method' => 'PATCH', 'path' => '/projects/{slug}/reports/{report}', 'path_params' => $project + $report,
                'body' => self::reportBody(creating: false),
                'title' => 'Update a report draft',
                'description' => 'Change a report\'s draft. blocks replaces the whole draft, so send every block you want to keep. Published versions do not change until you publish.',
            ],
            'reports_delete' => [
                'level' => self::FULL, 'method' => 'DELETE', 'path' => '/projects/{slug}/reports/{report}', 'path_params' => $project + $report,
                'title' => 'Delete a report',
                'description' => 'Delete a report. Its links, and every published version, stop opening.'.' It goes to the Trash in Framework Cloud, where the user can restore it. Delete only when the user asks, and name what you will delete first.',
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
    private static function blueprintBody(bool $preview = false): array
    {
        $choices = [
            'language' => ['type' => 'string', 'enum' => ['r', 'python', 'stata'], 'description' => 'The default language.'],
            'structure' => ['type' => 'string', 'description' => 'A structure key from blueprints_options, such as framework/project.'],
            'tools' => ['type' => 'object', 'additionalProperties' => ['type' => 'boolean'], 'description' => 'Tool keys from blueprints_options, each true to include, such as {"quarto": true, "renv": true}.'],
            'package_sets' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Package set keys.'],
            'skill_sets' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Skill set keys.'],
            'skills' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Skill keys, beyond those the skill sets add.'],
            'settings' => ['type' => 'object', 'properties' => [
                'sensitive' => ['type' => 'boolean', 'description' => 'Private and public data folders, and rules that keep private data from AI assistants.'],
                'git' => ['type' => 'boolean'],
                'ai' => ['type' => 'boolean', 'description' => 'Agent files and skills for AI assistants.'],
            ]],
            'extra_yaml' => ['type' => 'string', 'description' => 'More settings.yml settings, as YAML.'],
        ];
        if ($preview) {
            return [
                'properties' => $choices + ['workflow' => ['type' => 'string', 'enum' => ['quarto', 'jupyter', 'scripts'], 'description' => 'Notebooks with Quarto or Jupyter, or scripts only. Stata uses scripts.']],
                'required' => ['language', 'structure', 'workflow'],
            ];
        }

        return [
            'properties' => [
                'name' => ['type' => 'string', 'maxLength' => 100],
                'description' => ['type' => 'string', 'maxLength' => 1000],
                'visibility' => ['type' => 'string', 'enum' => ['private', 'unlisted', 'public'], 'description' => 'Defaults to private.'],
            ] + $choices + [
                'languages' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['r', 'python', 'stata']], 'description' => 'Languages projects from it may use. Leave out for all.'],
                'workflows' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['quarto', 'jupyter', 'scripts']], 'description' => 'Workflows projects from it may use. Leave out for all.'],
                'notes' => ['type' => 'string', 'description' => 'A short note for the version history.'],
            ],
            'required' => ['name', 'language', 'structure'],
        ];
    }

    /** @return array{properties: array<string, mixed>, required: list<string>} */
    private static function skillBody(): array
    {
        return [
            'properties' => [
                'content' => ['type' => 'string', 'description' => 'The whole SKILL.md: frontmatter (name:, description:) and the instructions. Up to 64 KB.'],
                'language' => ['type' => 'string', 'enum' => ['r', 'python', 'stata'], 'description' => 'For a skill with a version per language: which one this text is.'],
                'visibility' => ['type' => 'string', 'enum' => ['private', 'unlisted', 'public']],
                'notes' => ['type' => 'string', 'description' => 'A short note for the version history.'],
                'expected_version' => ['type' => 'integer', 'minimum' => 1, 'description' => 'For updates: the version you read. A newer one stops the save.'],
            ],
            'required' => ['content'],
        ];
    }

    /** @return array{properties: array<string, mixed>, required: list<string>} */
    private static function uploadBody(): array
    {
        $body = self::publishBody();
        unset($body['properties']['content'], $body['properties']['content_base64']);
        $body['properties']['filename']['description'] = 'The file name with its extension, as it is on disk, such as analysis.html or site.zip. The extension sets the kind.';

        return $body;
    }

    /** @return array{properties: array<string, mixed>, required: list<string>} */
    private static function publishBody(): array
    {
        return [
            'properties' => [
                'project' => ['type' => 'string', 'description' => 'The project to publish to, by slug. Leave out to publish with no project.'],
                'filename' => ['type' => 'string', 'description' => 'The file name with its extension, such as analysis.html, flipper-and-mass.png, or site.zip. The extension sets the kind.'],
                'content' => ['type' => 'string', 'description' => 'The file as text, for HTML, Markdown, CSV, JSON, or SVG.'],
                'content_base64' => ['type' => 'string', 'description' => 'The file, base64-encoded, for PNG, PDF, and other binary files. Keep it small; use artifacts_upload for larger files.'],
                'entrypoint' => ['type' => 'string', 'description' => 'For a zip: the HTML page to open, such as index.html.'],
                'artifact' => ['type' => 'string', 'description' => 'The artifact name in the link, such as penguin-body-mass. Defaults to the file name. Reuse a name to add a version.'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'visibility' => ['type' => 'string', 'enum' => ['private', 'unlisted', 'public'], 'description' => 'Who can open it. New artifacts start private. Ask the user before choosing public.'],
                'password' => ['type' => 'string', 'minLength' => 8],
                'kind' => ['type' => 'string', 'enum' => ['html', 'image', 'chart', 'table', 'data', 'model', 'test', 'text', 'pdf', 'file'], 'description' => 'Overrides the kind read from the file name.'],
            ],
            'required' => ['filename'],
        ];
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
                'artifact' => ['type' => 'string', 'description' => 'For artifact blocks: the published artifact\'s slug in the report\'s project, or anywhere in the account for a report with no project.'],
                'version_number' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Pin an artifact version. Defaults to the latest when published.'],
                'caption' => ['type' => 'string'],
                'share_unlisted' => ['type' => 'boolean', 'description' => 'Show an unlisted artifact in an unlisted report.'],
            ],
            'required' => ['type'],
        ];

        return [
            'properties' => ($creating ? ['project' => ['type' => 'string', 'description' => 'A project slug, from projects_list. Leave it out for a report with no project.']] : []) + [
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
