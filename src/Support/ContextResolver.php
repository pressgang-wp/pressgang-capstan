<?php

declare(strict_types=1);

namespace PressGang\Capstan\Support;

class ContextResolver
{
    /**
     * Walk upward from a starting directory looking for a WordPress root.
     *
     * Detection relies on the presence of wp-config.php or a wp-content/ directory.
     */
    public static function detectWordPressRoot(string $from): ?string
    {
        $dir = realpath($from);

        if ($dir === false) {
            return null;
        }

        while (true) {
            if (file_exists($dir . '/wp-config.php')) {
                return $dir;
            }

            if (is_dir($dir . '/wp-content')) {
                return $dir;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    /**
     * Build an immutable Context from the current environment.
     */
    public static function resolve(string $cwd): Context
    {
        $wpRoot = self::detectWordPressRoot($cwd);
        $themesDir = $wpRoot !== null ? $wpRoot . '/wp-content/themes' : null;

        return new Context(
            wpRoot: $wpRoot,
            themesDir: $themesDir,
            currentDir: $cwd,
        );
    }
}
