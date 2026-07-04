<?php

namespace PressGang\Capstan\Commands;

/**
 * Displays the resolved PressGang configuration — the merged parent/child
 * view the framework actually boots with, read from PressGang's own Config
 * facade (including anything applied by the pressgang_get_config filter).
 *
 * ## OPTIONS
 *
 * [<key>]
 * : A top-level config key (e.g. snippets, menus, custom-post-types).
 * Omit to list all keys.
 *
 * [--format=<format>]
 * : Output format for a key's value.
 * ---
 * default: json
 * options:
 *   - json
 *   - var_export
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan config dump
 *     wp capstan config dump snippets
 *     wp capstan config dump menus --format=var_export
 */
class ConfigDumpCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\Bootstrap\Config::class)) {
            \WP_CLI::error('PressGang 2 is not loaded — is a PressGang child theme active?');
        }

        $settings = (array) \PressGang\Bootstrap\Config::get();

        if ($args === []) {
            ksort($settings);

            $rows = array_map(fn (string $key, mixed $value) => [
                'key' => $key,
                'type' => get_debug_type($value),
                'entries' => is_array($value) ? (string) count($value) : '—',
            ], array_keys($settings), $settings);

            \WP_CLI\Utils\format_items('table', $rows, ['key', 'type', 'entries']);
            \WP_CLI::log('');
            \WP_CLI::log('Inspect a key with: wp capstan config dump <key>');

            return;
        }

        $key = $args[0];

        if (! array_key_exists($key, $settings)) {
            \WP_CLI::error("No config key '{$key}'. Available: " . implode(', ', array_keys($settings)));
        }

        $value = $settings[$key];

        // Config values may contain closures (e.g. route callbacks), which
        // JSON cannot represent — describe them instead of failing.
        $value = self::describe_closures($value);

        if (($assoc_args['format'] ?? 'json') === 'var_export') {
            \WP_CLI::log(var_export($value, true));

            return;
        }

        \WP_CLI::log((string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Replaces closures anywhere in a value with a readable placeholder.
     */
    private static function describe_closures(mixed $value): mixed
    {
        if ($value instanceof \Closure) {
            $reflection = new \ReflectionFunction($value);

            return sprintf('(closure %s:%d)', $reflection->getFileName(), $reflection->getStartLine());
        }

        if (is_array($value)) {
            return array_map(self::describe_closures(...), $value);
        }

        return $value;
    }
}
