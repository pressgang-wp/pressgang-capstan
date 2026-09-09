<?php

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Asserts capstan.php registers every command that declares
 * `@when before_wp_load` with the matching `when` option.
 *
 * WP-CLI reads `@when` from the docblock of the callable it registers. Capstan's
 * commands are `__invoke`-style classes, so an annotation on the *class*
 * docblock is never seen: without an explicit `['when' => 'before_wp_load']`
 * third argument to add_command(), the command demands a WordPress install.
 *
 * That silently broke every command meant to run before a site exists —
 * `capstan about`, `new`, `make child`, `theme package` and `make api-index`.
 * `make api-index` is why a package's docs/api-index.json can go stale: you
 * could not regenerate it from the package repo, only from inside some
 * unrelated WordPress install.
 */
class CommandRegistrationTest extends TestCase
{
    public function testEveryBeforeWpLoadCommandIsRegisteredAsSuch(): void
    {
        $annotated = $this->commandsAnnotatedBeforeWpLoad();
        $registered = $this->commandsRegisteredBeforeWpLoad();

        $this->assertNotSame([], $annotated, 'Expected at least one @when before_wp_load command.');

        $missing = array_values(array_diff($annotated, $registered));

        $this->assertSame([], $missing, sprintf(
            "These commands declare @when before_wp_load but are registered without "
            . "['when' => 'before_wp_load'] in capstan.php, so WP-CLI will demand a "
            . "WordPress install: %s",
            implode(', ', $missing)
        ));
    }

    public function testNoCommandIsRegisteredBeforeWpLoadWithoutTheAnnotation(): void
    {
        $extra = array_values(array_diff(
            $this->commandsRegisteredBeforeWpLoad(),
            $this->commandsAnnotatedBeforeWpLoad()
        ));

        $this->assertSame([], $extra, sprintf(
            'These commands are registered before_wp_load but do not document it '
            . 'with @when before_wp_load: %s',
            implode(', ', $extra)
        ));
    }

    /**
     * Command classes whose docblock declares `@when before_wp_load`.
     *
     * @return list<string> Short class names, sorted.
     */
    private function commandsAnnotatedBeforeWpLoad(): array
    {
        $names = [];

        foreach (glob(\dirname(__DIR__) . '/src/Commands/*.php') ?: [] as $file) {
            $class = 'PressGang\\Capstan\\Commands\\' . basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $doc = (new ReflectionClass($class))->getDocComment();

            if (is_string($doc) && str_contains($doc, '@when before_wp_load')) {
                $names[] = basename($file, '.php');
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Command classes capstan.php passes `['when' => 'before_wp_load']` for.
     *
     * Parsed rather than executed: capstan.php returns early unless WP_CLI is
     * defined, and defining it would pull in the CLI runtime.
     *
     * @return list<string> Short class names, sorted.
     */
    private function commandsRegisteredBeforeWpLoad(): array
    {
        $source = (string) file_get_contents(\dirname(__DIR__) . '/capstan.php');

        preg_match_all(
            '/add_command\(\s*\'[^\']+\'\s*,\s*\\\\?PressGang\\\\Capstan\\\\Commands\\\\(\w+)::class\s*,\s*\$before_wp_load\s*\)/',
            $source,
            $matches
        );

        $names = $matches[1];
        sort($names);

        return $names;
    }
}
