<?php

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class);
WP_CLI::add_command('capstan new', \PressGang\Capstan\Commands\NewCommand::class);
WP_CLI::add_command('capstan make', \PressGang\Capstan\Commands\MakeCommand::class);
WP_CLI::add_command('capstan make child', \PressGang\Capstan\Commands\MakeChildCommand::class);
WP_CLI::add_command('capstan theme', \PressGang\Capstan\Commands\ThemeCommand::class);
WP_CLI::add_command('capstan theme package', \PressGang\Capstan\Commands\ThemePackageCommand::class);
