<?php

declare(strict_types=1);

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class);
