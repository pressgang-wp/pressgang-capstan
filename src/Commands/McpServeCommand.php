<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Mcp\Server;
use PressGang\Capstan\Mcp\ToolRegistry;

/**
 * Run a Model Context Protocol server exposing Capstan's theme introspection
 * to any MCP-speaking editor (Claude Code, Cursor, Windsurf, …).
 *
 * Speaks JSON-RPC 2.0 over stdio. You do not normally run this by hand —
 * `wp bosun install` writes the registration (`.mcp.json` / `.cursor/mcp.json`)
 * that launches it. See ADR 0001 in pressgang-bosun for the tool surface.
 *
 * Read-only by default: every default tool is a thin proxy over an existing
 * `wp capstan … --format=json` command. Write tools (eval, scaffolding) are
 * gated behind --allow-write and never start in a production environment.
 *
 * ## OPTIONS
 *
 * [--allow-write]
 * : Expose the gated write tools (eval, make). Off by default.
 *
 * ## EXAMPLES
 *
 *     wp capstan mcp serve
 *     wp capstan mcp serve --allow-write
 *
 * @when after_wp_load
 */
class McpServeCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') {
            \WP_CLI::error('Refusing to start the MCP server in a production environment.');
        }

        if (! class_exists(\PressGang\Bootstrap\Config::class)) {
            \WP_CLI::error('PressGang 2 is not loaded — activate a PressGang child theme first.');
        }

        $allow_write = (bool) ($assoc_args['allow-write'] ?? false);

        $registry = new ToolRegistry(allowWrite: $allow_write);
        $server   = new Server($registry, version: $this->version());

        // STDOUT is the protocol channel — diagnostics go to STDERR only.
        fwrite(STDERR, '[capstan mcp] serving on stdio (write tools: ' . ($allow_write ? 'on' : 'off') . ")\n");

        $server->serve(STDIN, STDOUT, STDERR);
    }

    private function version(): string
    {
        return \PressGang\Capstan\CapstanCommand::version();
    }
}
