<?php

namespace App\Mcp;

use App\Models\McpConnection;

/**
 * Checks each tool call against the connection's access level. The tool list
 * already leaves out tools above the level; this checks each call as well.
 */
class AccessPolicy
{
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

        return null;
    }
}
