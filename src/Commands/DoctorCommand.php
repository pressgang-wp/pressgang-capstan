<?php

namespace PressGang\Capstan\Commands;

/**
 * Diagnoses common PressGang theme configuration issues.
 *
 * Every check is deterministic — filesystem and class-map facts, no
 * heuristics — so a clean bill of health means something.
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format. `json` emits {checks, failures, warnings} for harnesses
 * (e.g. shakedown's pre-flight) and exits non-zero on failures without the
 * human summary lines.
 * ---
 * default: table
 * options:
 *   - table
 *   - json
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan doctor
 *     wp capstan doctor --format=json
 */
class DoctorCommand
{
    /** @var array<int, array{check: string, status: string, detail: string}> */
    private array $results = [];

    public function __invoke(array $args, array $assoc_args): void
    {
        $theme = get_stylesheet_directory();

        $this->check(
            'Child theme active',
            get_stylesheet_directory() !== get_template_directory(),
            basename($theme),
            'the active theme is not a child theme'
        );

        $this->check(
            'Composer autoload',
            is_file("{$theme}/vendor/autoload.php"),
            'vendor/autoload.php present',
            'missing — run composer install in the theme'
        );

        $this->check(
            'No legacy v1 boot',
            ! is_file("{$theme}/core/settings.php"),
            'no core/settings.php',
            'core/settings.php present — theme still boots PressGang v1 (see the pressgang-v1-migration skill)'
        );

        $namespace = function_exists('get_child_theme_namespace') ? \get_child_theme_namespace() : null;

        $this->check(
            'Child namespace',
            $namespace !== null,
            (string) $namespace,
            'not resolvable — set THEMENAMESPACE or a PSR-4 entry in the theme composer.json'
        );

        // Compute before check(): arguments evaluate eagerly, and touching
        // Timber::$version when the class is absent would fatal.
        $timber = class_exists(\Timber\Timber::class);

        $this->check(
            'Timber',
            $timber,
            $timber ? 'Timber ' . (\Timber\Timber::$version ?? '2.x') : '',
            'Timber\\Timber class not found'
        );

        $this->check(
            'Views directory',
            is_dir("{$theme}/views"),
            'views/ present',
            'views/ missing — Twig templates have nowhere to live'
        );

        if (class_exists(\PressGang\Bootstrap\Config::class)) {
            $this->check_config_classes($namespace);
        } else {
            $this->results[] = [
                'check' => 'PressGang config',
                'status' => 'FAIL',
                'detail' => 'PressGang\\Bootstrap\\Config not loaded — parent theme missing or theme not booting PressGang 2',
            ];
        }

        $failures = array_filter($this->results, fn (array $row) => $row['status'] === 'FAIL');
        $warnings = array_filter($this->results, fn (array $row) => $row['status'] === 'WARN');

        if (($assoc_args['format'] ?? 'table') === 'json') {
            \WP_CLI::log((string) json_encode([
                'checks' => $this->results,
                'failures' => count($failures),
                'warnings' => count($warnings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($failures) {
                \WP_CLI::halt(1);
            }

            return;
        }

        \WP_CLI\Utils\format_items('table', $this->results, ['check', 'status', 'detail']);

        if ($failures) {
            \WP_CLI::error(count($failures) . ' check(s) failed.');
        }

        if ($warnings) {
            \WP_CLI::warning(count($warnings) . ' warning(s) — seaworthy, but worth a look.');

            return;
        }

        \WP_CLI::success('All checks passed. Shipshape and Bristol fashion.');
    }

    /**
     * Checks that every class referenced in config actually exists.
     */
    private function check_config_classes(?string $namespace): void
    {
        // Snippets resolve through the same ClassResolver the framework uses.
        $missing = [];

        foreach (array_keys((array) \PressGang\Bootstrap\Config::get('snippets', [])) as $snippet) {
            if (\PressGang\Util\ClassResolver::resolve((string) $snippet, 'Snippets', $namespace) === null) {
                $missing[] = $snippet;
            }
        }

        $this->check(
            'Snippet classes',
            $missing === [],
            count((array) \PressGang\Bootstrap\Config::get('snippets', [])) . ' resolve',
            'unresolved (silently skipped at boot): ' . implode(', ', $missing)
        );

        // Service providers, controller map, and route handlers are FQCNs.
        foreach ([
            'Service providers' => array_values((array) \PressGang\Bootstrap\Config::get('service-providers', [])),
            'Controller map' => array_values((array) \PressGang\Bootstrap\Config::get('controllers', [])),
            'Route handlers' => array_filter(array_values((array) \PressGang\Bootstrap\Config::get('routes', [])), 'is_string'),
        ] as $label => $classes) {
            $absent = array_filter($classes, fn ($class) => is_string($class) && ! class_exists($class));

            $this->check(
                $label,
                $absent === [],
                count($classes) . ' class(es) exist',
                'missing class(es): ' . implode(', ', $absent)
            );
        }

        // File-shaped page-template ids that collide with real files are
        // shadowed by WordPress's file-based template discovery.
        $theme = get_stylesheet_directory();
        $shadowed = array_filter(
            array_keys((array) \PressGang\Bootstrap\Config::get('page-templates', [])),
            fn ($id) => is_string($id) && str_ends_with($id, '.php') && is_file("{$theme}/{$id}")
        );

        $this->check(
            'Page templates',
            $shadowed === [],
            count((array) \PressGang\Bootstrap\Config::get('page-templates', [])) . ' registered file-lessly',
            'physical files shadow config-registered ids: ' . implode(', ', $shadowed),
            'WARN'
        );
    }

    /**
     * Records a check result.
     *
     * @param string $check    Check label.
     * @param bool   $passed   Whether it passed.
     * @param string $ok       Detail when passing.
     * @param string $problem  Detail when not.
     * @param string $severity Status when not passing (FAIL or WARN).
     */
    private function check(string $check, bool $passed, string $ok, string $problem, string $severity = 'FAIL'): void
    {
        $this->results[] = [
            'check' => $check,
            'status' => $passed ? 'OK' : $severity,
            'detail' => $passed ? $ok : $problem,
        ];
    }
}
