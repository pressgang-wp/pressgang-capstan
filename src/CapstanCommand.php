<?php

namespace PressGang\Capstan;

/**
 * Root namespace for `wp capstan` commands.
 *
 * Subcommand registration is handled in capstan.php via explicit
 * WP_CLI::add_command() calls. This class exists only as a namespace
 * anchor; it does not implement any logic.
 */
class CapstanCommand
{
    public const VERSION = '0.3.0';
}
