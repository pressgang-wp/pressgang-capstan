<?php

namespace PressGang\Capstan\Support;

/**
 * Bridges scaffolding to pressgang-bosun: after a theme's dependencies are
 * installed, attempt `wp bosun install` so the new theme starts life with
 * composed AI agent guidelines. Degrades to a tip when bosun isn't
 * installed — Capstan never hard-depends on it.
 */
final class BosunBriefing
{
    /**
     * Attempts to compose agent guidelines for a theme via bosun.
     *
     * @param string $themeDir Absolute path to the child theme.
     * @return void
     */
    public static function attempt(string $themeDir): void
    {
        $result = \WP_CLI::runcommand(
            'bosun install --theme=' . escapeshellarg($themeDir),
            [
                'launch' => true,
                'exit_error' => false,
                'return' => 'all',
            ],
        );

        if (is_object($result) && (int) $result->return_code === 0) {
            \WP_CLI::log('→ Composed AI agent guidelines (pressgang-bosun).');

            return;
        }

        self::tip();
    }

    /**
     * Prints the manual bosun tip for when it isn't installed (or a theme's
     * dependencies aren't in place yet).
     *
     * @return void
     */
    public static function tip(): void
    {
        \WP_CLI::log('  Tip: brief AI agents on the theme with pressgang-bosun:');
        \WP_CLI::log('       wp package install https://github.com/pressgang-wp/pressgang-bosun.git');
        \WP_CLI::log('       wp bosun install');
    }
}
