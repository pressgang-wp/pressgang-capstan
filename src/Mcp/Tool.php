<?php

namespace PressGang\Capstan\Mcp;

/**
 * A single MCP tool: the schema an editor lists, and the handler that runs it.
 */
final class Tool
{
    /**
     * @param array           $inputSchema JSON Schema for the tool's arguments
     * @param callable(array): array $handler returns MCP `tools/call` content
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public mixed $handler,
    ) {
    }
}
