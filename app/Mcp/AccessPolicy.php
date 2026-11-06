<?php

namespace App\Mcp;

use App\Models\McpConnection;

/**
 * Checks each tool call against the connection's access level. The tool list
 * already leaves out tools above the level; this also stops a "Drafts only"
 * connection from publishing or changing who can read a report.
 */
class AccessPolicy
{
    /** Fields that change what readers see. */
    private const READER_FIELDS = ['publish', 'visibility', 'password', 'remove_password'];

    /**
     * @param  array<string, mixed>  $tool  from ToolCatalog::tools()
     * @param  array<string, mixed>  $body
     * @return array{code: string, message: string}|null a refusal, or null to allow
     */
    public static function check(McpConnection $connection, string $name, array $tool, array $body): ?array
    {
        if (! ToolCatalog::allows($connection->access, $tool['level'])) {
            return ['code' => 'mcp_tool_not_allowed', 'message' => "This connection's access (".McpConnection::accessLabel($connection->access).") does not allow {$name}."];
        }

        if ($connection->access === McpConnection::ACCESS_DRAFTS && $tool['level'] === ToolCatalog::DRAFTS) {
            $sent = array_values(array_intersect(self::READER_FIELDS, array_keys($body)));
            $sent = array_values(array_filter($sent, fn (string $field) => ! ($field === 'publish' && $body['publish'] === false)));

            if ($sent !== []) {
                return [
                    'code' => 'mcp_drafts_only',
                    'message' => 'This connection may only work on drafts. Leave out '.implode(', ', $sent).', and tell the user the draft is ready to review and publish.',
                ];
            }
        }

        return null;
    }
}
