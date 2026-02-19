<?php

namespace PressGang\Capstan\Commands;

/**
 * Manage PressGang themes.
 *
 * ## EXAMPLES
 *
 *     wp capstan theme package
 *
 * @when before_wp_load
 */
class ThemeCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        \WP_CLI::log('Usage: wp capstan theme <subcommand>');
        \WP_CLI::log('');
        \WP_CLI::log('Available subcommands:');
        \WP_CLI::log('  package   Create a WordPress-uploadable ZIP from a theme directory');
    }
}
