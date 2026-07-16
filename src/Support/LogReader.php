<?php

namespace PressGang\Capstan\Support;

/**
 * Reads the tail of the WordPress debug log — the environment-native source of
 * recent PHP errors and warnings, and the `pressgang_logs` MCP tool's backing.
 *
 * The path resolves from `WP_DEBUG_LOG` (when set to a file) or the
 * conventional `wp-content/debug.log`; an explicit path can be injected for
 * testing. A missing log is reported, never an error — logging may simply be
 * off.
 */
final class LogReader
{
    public function __construct(private ?string $path = null)
    {
    }

    private function resolve_path(): ?string
    {
        if ($this->path !== null) {
            return $this->path;
        }

        if (defined('WP_DEBUG_LOG') && is_string(\WP_DEBUG_LOG) && \WP_DEBUG_LOG !== '') {
            return \WP_DEBUG_LOG;
        }

        if (defined('WP_CONTENT_DIR')) {
            return \WP_CONTENT_DIR . '/debug.log';
        }

        return null;
    }

    /**
     * The last $lines entries of the debug log.
     *
     * @return array{path: string|null, exists: bool, lines: list<string>}
     */
    public function tail(int $lines = 50): array
    {
        $path = $this->resolve_path();
        $lines = max(1, $lines);

        if ($path === null || ! is_file($path)) {
            return ['path' => $path, 'exists' => false, 'lines' => []];
        }

        $all = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        return [
            'path' => $path,
            'exists' => true,
            'lines' => array_values(array_slice($all, -$lines)),
        ];
    }
}
