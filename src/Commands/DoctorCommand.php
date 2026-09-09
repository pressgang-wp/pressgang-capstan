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
            $this->check_replaced_parent_config();
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
     * Config files whose parent entries are framework wiring, not site content.
     *
     * A child theme is *meant* to replace config/menus.php or
     * config/acf-options.php wholesale — those describe the site. These five
     * describe how the framework is assembled, so a parent entry the child
     * does not repeat is almost always an accident.
     */
    private const WIRING_CONFIG = [
        'service-providers',
        'twig-extensions',
        'context-managers',
        'timber-class-map',
        'timber',
    ];

    /**
     * Warns when a child config file drops framework wiring the parent declared.
     *
     * PressGang merges config by filename, not by key: a child
     * `config/x.php` REPLACES the parent's file of the same name, and any
     * parent entry the child does not repeat is silently dropped. A child
     * `service-providers.php` without TimberServiceProvider is the worst
     * case — every context manager, Twig extension and Twig environment
     * option disappears, and the site still boots and renders.
     *
     * Deterministic: both files are read from disk and compared by identity
     * (list values and array keys, recursively), restricted to the wiring
     * files above. WooCommerce entries are skipped when WooCommerce is not
     * active, since the framework gates those managers on the same fact.
     *
     * Overriding a wiring default on purpose is legitimate, so this warns
     * rather than fails — except for TimberServiceProvider, which nothing
     * else supplies.
     */
    private function check_replaced_parent_config(): void
    {
        $parent_dir = rtrim(get_template_directory(), '/') . '/config';
        $child_dir = rtrim(get_stylesheet_directory(), '/') . '/config';

        if ($parent_dir === $child_dir || ! is_dir($child_dir)) {
            return;
        }

        $woocommerce = class_exists('WooCommerce');
        $dropped = [];

        foreach (self::WIRING_CONFIG as $name) {
            $child_file = "{$child_dir}/{$name}.php";
            $parent_file = "{$parent_dir}/{$name}.php";

            if (! is_file($child_file) || ! is_file($parent_file)) {
                continue;
            }

            $missing = array_values(array_filter(
                array_diff(
                    $this->config_identities($parent_file),
                    $this->config_identities($child_file)
                ),
                fn (string $entry): bool => $woocommerce || ! str_contains($entry, 'WooCommerce')
            ));

            if ($missing !== []) {
                $dropped[$name] = $missing;
            }
        }

        $breaks_timber = in_array(
            'PressGang\\ServiceProviders\\TimberServiceProvider',
            $dropped['service-providers'] ?? [],
            true
        );

        $detail = [];
        foreach ($dropped as $name => $missing) {
            $detail[] = "config/{$name}.php: " . implode(', ', $missing);
        }

        $this->check(
            'Parent config wiring',
            $dropped === [],
            'child config re-declares the parent defaults it replaces',
            'child config replaces the parent file without re-declaring: ' . implode(' | ', $detail),
            $breaks_timber ? 'FAIL' : 'WARN'
        );
    }

    /**
     * Identity tokens for a config file: list values plus array keys, nested.
     *
     * Covers both config shapes — lists of class strings
     * (service-providers, twig-extensions, context-managers) and keyed maps
     * (timber-class-map, timber) — without knowing which is which.
     *
     * @param string $file Absolute path to a config file.
     *
     * @return list<string>
     */
    private function config_identities(string $file): array
    {
        $config = require $file;

        return is_array($config) ? array_values(array_unique($this->identities($config))) : [];
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<string>
     */
    private function identities(array $config): array
    {
        $identities = [];

        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $identities[] = $key;
            }

            if (is_array($value)) {
                $identities = array_merge($identities, $this->identities($value));
            } elseif (is_string($value) && is_int($key)) {
                $identities[] = $value;
            }
        }

        return $identities;
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
