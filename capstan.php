<?php

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class);
WP_CLI::add_command('capstan new', \PressGang\Capstan\Commands\NewCommand::class);
WP_CLI::add_command('capstan make child', \PressGang\Capstan\Commands\MakeChildCommand::class);
WP_CLI::add_command('capstan theme package', \PressGang\Capstan\Commands\ThemePackageCommand::class);
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
