<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\CapstanCommand;
use PressGang\Capstan\Support\ContextResolver;

/**
 * Display information about Capstan.
 *
 * ## EXAMPLES
 *
 *     wp capstan about
 *
 * @when before_wp_load
 */
class AboutCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $cwd = getcwd();
        $context = ContextResolver::resolve($cwd);

        \WP_CLI::log('Capstan version ' . CapstanCommand::VERSION);
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
