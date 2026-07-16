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
    /**
     * Capstan's version, derived from the installed package metadata (the git
     * tag Composer resolved) — never hand-maintained. 'dev' from a working
     * clone with no derivable tag.
     */
    public static function version(): string
    {
        return \PressGang\Capstan\Support\PackageVersion::of('pressgang-wp/capstan');
    }
}
