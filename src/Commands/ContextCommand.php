<?php

namespace PressGang\Capstan\Commands;

/**
 * Shows a controller's context contract: its declared context getter
 * manifest and the getters available to it. With --add, publishes keys
 * into the manifest.
 *
 * Inspection is pure reflection — the controller is never instantiated.
 * Manifest edits are conservative and explicit: you name the keys
 * (auto-publishing every getter is exactly what the manifest exists to
 * avoid), only theme-owned files are edited, and the rewritten file is
 * lint-checked before it replaces the original. Dry-run by default.
 *
 * ## OPTIONS
 *
 * <controller>
 * : Controller name (e.g. FrontPage, FrontPageController) or a fully
 * qualified class name.
 *
 * [--add=<keys>]
 * : Comma-separated context keys to publish. Each needs a get_{key}()
 * method on the controller.
 *
 * [--add-all]
 * : Publish every theme getter not already in the manifest.
 *
 * [--force]
 * : Write the manifest change (omit to preview).
 *
 * [--format=<format>]
 * : Output format for inspection (ignored with --add). `json` emits the
 * manifest, framework overrides, and unmapped getters as structured data.
 * ---
 * default: log
 * options:
 *   - log
 *   - json
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan context FrontPage
 *     wp capstan context FrontPage --format=json
 *     wp capstan context FrontPage --add=news,events
 *     wp capstan context FrontPage --add=news,events --force
 */
class ContextCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $class = $this->resolve_class($args[0]);

        if ($class === null) {
            \WP_CLI::error("No controller class found for '{$args[0]}'.");
        }

        $reflection = new \ReflectionClass($class);

        if (isset($assoc_args['add']) || isset($assoc_args['add-all'])) {
            $this->publish($reflection, $assoc_args);

            return;
        }
        $manifest_decl = $reflection->getDefaultProperties()['context_getters'] ?? [];
        $template = $reflection->getDefaultProperties()['template'] ?? null;

        $manifest = [];
        $mapped_methods = [];

        foreach ((array) $manifest_decl as $key => $method) {
            if (is_int($key)) {
                [$key, $method] = [$method, "get_{$method}"];
            }

            $mapped_methods[] = $method;
            $has = $reflection->hasMethod($method);
            $manifest[] = [
                'context_key' => $key,
                'getter' => $method,
                'missing' => ! $has,
                'declared_in' => $has ? $this->declared_in($reflection->getMethod($method)) : null,
            ];
        }

        $overrides = [];
        $unmapped = [];

        foreach ($reflection->getMethods() as $method) {
            if (
                ! str_starts_with($method->getName(), 'get_')
                || in_array($method->getName(), $mapped_methods, true)
                || str_starts_with((string) $method->getDeclaringClass()->getName(), 'PressGang\\')
                || in_array($method->getName(), ['get_context', 'get_template'], true)
            ) {
                continue;
            }

            if ($parent = $this->pressgang_parent_declaring($reflection, $method->getName())) {
                $overrides[] = ['method' => $method->getName(), 'overrides' => $parent];
            } else {
                $unmapped[] = ['method' => $method->getName(), 'declared_in' => $this->declared_in($method)];
            }
        }

        if (($assoc_args['format'] ?? 'log') === 'json') {
            \WP_CLI::log((string) json_encode([
                'controller' => $class,
                'source' => $reflection->getFileName(),
                'extends' => $reflection->getParentClass()?->getName(),
                'template' => $template ?: null,
                'manifest' => $manifest,
                'framework_overrides' => $overrides,
                'unmapped_getters' => $unmapped,
            ]));

            return;
        }

        \WP_CLI::log("Controller: {$class}");
        \WP_CLI::log('Source:     ' . $reflection->getFileName());
        \WP_CLI::log('Extends:    ' . ($reflection->getParentClass()?->getName() ?? '—'));
        \WP_CLI::log('Template:   ' . ($template ?: '(inferred)'));
        \WP_CLI::log('');

        if ($manifest) {
            \WP_CLI::log('Context manifest ($context_getters):');
            $rows = array_map(fn (array $m): array => [
                'context key' => $m['context_key'],
                'getter' => $m['getter'] . ($m['missing'] ? '  (MISSING)' : ''),
                'declared in' => $m['declared_in'] ?? '—',
            ], $manifest);
            \WP_CLI\Utils\format_items('table', $rows, ['context key', 'getter', 'declared in']);
        } else {
            \WP_CLI::log('Context manifest: (none — context comes from get_context() and parent controllers)');
        }

        if ($overrides) {
            \WP_CLI::log('');
            \WP_CLI::log('Framework getter overrides (feed the parent controller\'s context):');

            foreach ($overrides as $o) {
                \WP_CLI::log("  {$o['method']}()  — overrides {$o['overrides']}");
            }
        }

        if ($unmapped) {
            \WP_CLI::log('');
            \WP_CLI::log('Getters not in the manifest (available, but not auto-published):');

            foreach ($unmapped as $u) {
                \WP_CLI::log("  {$u['method']}()  — {$u['declared_in']}");
            }
        }
    }

    /**
     * The PressGang ancestor class short name that also declares a method,
     * or null when the method is the theme's own.
     *
     * @param \ReflectionClass $class  The controller class.
     * @param string           $method Method name.
     * @return string|null
     */
    private function pressgang_parent_declaring(\ReflectionClass $class, string $method): ?string
    {
        for ($parent = $class->getParentClass(); $parent; $parent = $parent->getParentClass()) {
            if (str_starts_with($parent->getName(), 'PressGang\\') && $parent->hasMethod($method)) {
                return $parent->getShortName();
            }
        }

        return null;
    }

    /**
     * Publishes context keys into the controller's manifest source.
     */
    private function publish(\ReflectionClass $reflection, array $assoc_args): void
    {
        $file = (string) $reflection->getFileName();
        $theme = get_stylesheet_directory();

        if (! str_starts_with($file, $theme . '/')) {
            \WP_CLI::error("{$reflection->getName()} lives outside the active theme ({$file}) — capstan only edits theme-owned controllers.");
        }

        $keys = isset($assoc_args['add'])
            ? array_values(array_filter(array_map('trim', explode(',', (string) $assoc_args['add']))))
            : $this->unpublished_keys($reflection);

        if ($keys === []) {
            \WP_CLI::success('Nothing to publish — the manifest already covers every theme getter.');

            return;
        }

        $missing = array_filter($keys, fn (string $key) => ! $reflection->hasMethod("get_{$key}"));

        if ($missing) {
            \WP_CLI::error('No getter for: ' . implode(', ', array_map(fn ($k) => "get_{$k}()", $missing)) . ' — the manifest must not point at methods that do not exist.');
        }

        $source = (string) file_get_contents($file);
        $updated = \PressGang\Capstan\Support\ManifestWriter::add($source, $keys);

        if ($updated === null) {
            \WP_CLI::warning('The $context_getters declaration is not a shape capstan will edit — add the keys manually:');
            \WP_CLI::log("\tprotected array \$context_getters = [ '" . implode("', '", $keys) . "' ];");
            \WP_CLI::halt(1);
        }

        if ($updated === $source) {
            \WP_CLI::success('Manifest already up to date.');

            return;
        }

        \WP_CLI::log('Would publish: ' . implode(', ', $keys));
        \WP_CLI::log('');

        foreach (array_diff(explode("\n", $updated), explode("\n", $source)) as $line) {
            \WP_CLI::log("  + {$line}");
        }

        if (! isset($assoc_args['force'])) {
            \WP_CLI::log('');
            \WP_CLI::log('Dry run — re-run with --force to write.');

            return;
        }

        // Lint the rewrite before it replaces the original.
        $staged = $file . '.capstan-staged';
        file_put_contents($staged, $updated);
        exec('php -l ' . escapeshellarg($staged) . ' 2>&1', $output, $exit);

        if ($exit !== 0) {
            unlink($staged);
            \WP_CLI::error('Rewritten controller failed php -l — nothing written. ' . implode(' ', $output));
        }

        rename($staged, $file);

        \WP_CLI::success('Published. Verify with: wp capstan context ' . $reflection->getShortName());
    }

    /**
     * Context keys for every theme getter not already in the manifest.
     *
     * @return array<int, string>
     */
    private function unpublished_keys(\ReflectionClass $reflection): array
    {
        $manifest = (array) ($reflection->getDefaultProperties()['context_getters'] ?? []);
        $mapped = array_map(
            fn ($key, $method) => is_int($key) ? "get_{$method}" : $method,
            array_keys($manifest),
            $manifest
        );

        $keys = [];

        foreach ($reflection->getMethods() as $method) {
            if (
                str_starts_with($method->getName(), 'get_')
                && ! in_array($method->getName(), $mapped, true)
                && ! str_starts_with((string) $method->getDeclaringClass()->getName(), 'PressGang\\')
                && ! in_array($method->getName(), ['get_context', 'get_template'], true)
                && $this->pressgang_parent_declaring($reflection, $method->getName()) === null
            ) {
                $keys[] = substr($method->getName(), 4);
            }
        }

        return $keys;
    }

    /**
     * Resolves the controller class from a short name or FQCN.
     *
     * @param string $name Controller name as given.
     * @return class-string|null
     */
    private function resolve_class(string $name): ?string
    {
        if (class_exists($name)) {
            return $name;
        }

        $base = str_ends_with($name, 'Controller') ? $name : "{$name}Controller";
        $namespace = function_exists('get_child_theme_namespace') ? \get_child_theme_namespace() : null;

        foreach ([$namespace, 'PressGang'] as $prefix) {
            if ($prefix && class_exists("{$prefix}\\Controllers\\{$base}")) {
                return "{$prefix}\\Controllers\\{$base}";
            }
        }

        return null;
    }

    /**
     * A short label for where a method is declared: the class basename,
     * with '(trait X)' when it comes from one.
     *
     * @param \ReflectionMethod $method
     * @return string
     */
    private function declared_in(\ReflectionMethod $method): string
    {
        $class = $method->getDeclaringClass();

        foreach ($class->getTraits() as $trait) {
            if ($trait->hasMethod($method->getName())) {
                $trait_method = $trait->getMethod($method->getName());

                if ($trait_method->getFileName() === $method->getFileName()) {
                    return $class->getShortName() . ' (trait ' . $trait->getShortName() . ')';
                }
            }
        }

        return $class->getShortName();
    }
}
