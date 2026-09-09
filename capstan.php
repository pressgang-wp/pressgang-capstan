<?php

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

/**
 * Commands that must run without a WordPress installation.
 *
 * WP-CLI reads `@when` from the docblock of the callable it registers. These
 * are `__invoke`-style command classes, so the annotation on the *class*
 * docblock is never seen and the `when` option has to be passed here —
 * otherwise every command demands a WordPress install, including the
 * scaffolding and package tooling that exists precisely to run before one is
 * present.
 *
 * Keep this list in step with the `@when before_wp_load` annotations in
 * src/Commands; CommandRegistrationTest asserts the two agree.
 */
$before_wp_load = ['when' => 'before_wp_load'];

// No WordPress required: scaffolding, packaging, package tooling.
WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class, $before_wp_load);
WP_CLI::add_command('capstan new', \PressGang\Capstan\Commands\NewCommand::class, $before_wp_load);
WP_CLI::add_command('capstan make child', \PressGang\Capstan\Commands\MakeChildCommand::class, $before_wp_load);
WP_CLI::add_command('capstan theme package', \PressGang\Capstan\Commands\ThemePackageCommand::class, $before_wp_load);
WP_CLI::add_command('capstan make api-index', \PressGang\Capstan\Commands\MakeApiIndexCommand::class, $before_wp_load);

// Runtime introspection and scaffolding that reads the booted theme.
WP_CLI::add_command('capstan resolve', \PressGang\Capstan\Commands\ResolveCommand::class);
WP_CLI::add_command('capstan matrix', \PressGang\Capstan\Commands\MatrixCommand::class);
WP_CLI::add_command('capstan context', \PressGang\Capstan\Commands\ContextCommand::class);
WP_CLI::add_command('capstan config dump', \PressGang\Capstan\Commands\ConfigDumpCommand::class);
WP_CLI::add_command('capstan snippets', \PressGang\Capstan\Commands\SnippetsCommand::class);
WP_CLI::add_command('capstan doctor', \PressGang\Capstan\Commands\DoctorCommand::class);
WP_CLI::add_command('capstan make cpt', \PressGang\Capstan\Commands\MakeCptCommand::class);
WP_CLI::add_command('capstan make block', \PressGang\Capstan\Commands\MakeBlockCommand::class);
WP_CLI::add_command('capstan make controller', \PressGang\Capstan\Commands\MakeControllerCommand::class);
WP_CLI::add_command('capstan make muster', \PressGang\Capstan\Commands\MakeMusterCommand::class);
WP_CLI::add_command('capstan make recipe', \PressGang\Capstan\Commands\MakeRecipeCommand::class);
WP_CLI::add_command('capstan mcp serve', \PressGang\Capstan\Commands\McpServeCommand::class);
