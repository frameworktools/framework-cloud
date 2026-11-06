<?php

namespace App\Support;

/**
 * Whether AI agents may connect (the hosted MCP server, its OAuth sign-in, and
 * the Agents page). FW_AGENTS sets it; without it, Server administration can
 * save it; on by default. Off, existing connections stop working until it is
 * turned on again.
 */
class Agents
{
    public static function enabled(): bool
    {
        return filter_var(config('framework.agents', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
