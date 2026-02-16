<?php

declare(strict_types=1);

namespace PressGang\Capstan\Commands;

/**
 * Scaffold PressGang theme assets.
 *
 * ## EXAMPLES
 *
 *     wp capstan make child my-theme
 *
 * @when before_wp_load
 */
class MakeCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        \WP_CLI::log('Usage: wp capstan make <subcommand>');
        \WP_CLI::log('');
        \WP_CLI::log('Available subcommands:');
        \WP_CLI::log('  child   Scaffold a new PressGang child theme');
    }
}
