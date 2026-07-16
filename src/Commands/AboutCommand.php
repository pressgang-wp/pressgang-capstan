<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\CapstanCommand;
use PressGang\Capstan\Support\ContextResolver;

/**
 * Display information about Capstan.
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format. `json` emits the same fields as structured data.
 * ---
 * default: log
 * options:
 *   - log
 *   - json
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan about
 *     wp capstan about --format=json
 *
 * @when before_wp_load
 */
class AboutCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $cwd = getcwd();
        $context = ContextResolver::resolve($cwd);

        if (($assoc_args['format'] ?? 'log') === 'json') {
            \WP_CLI::log((string) json_encode([
                'version' => CapstanCommand::version(),
                'php' => PHP_VERSION,
                'working_dir' => $cwd,
                'wp_root' => $context->wpRoot,
            ]));

            return;
        }

        \WP_CLI::log('Capstan version ' . CapstanCommand::version());
        \WP_CLI::log('');
        \WP_CLI::log('PHP version:    ' . PHP_VERSION);
        \WP_CLI::log('Working dir:    ' . $cwd);

        if ($context->wpRoot !== null) {
            \WP_CLI::log('WordPress root: ' . $context->wpRoot);
        } else {
            \WP_CLI::warning('WordPress root not detected.');
        }
    }
}
