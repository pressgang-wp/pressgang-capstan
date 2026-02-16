<?php

declare(strict_types=1);

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class);
WP_CLI::add_command('capstan make', \PressGang\Capstan\Commands\MakeCommand::class);
WP_CLI::add_command('capstan make child', \PressGang\Capstan\Commands\MakeChildCommand::class);
