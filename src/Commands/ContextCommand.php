<?php

namespace PressGang\Capstan\Commands;

/**
 * Shows a controller's context contract: its declared context getter
 * manifest and the getters available to it.
 *
 * Pure reflection — the controller is never instantiated, so inspecting it
 * has no side effects.
 *
 * ## OPTIONS
 *
 * <controller>
 * : Controller name (e.g. FrontPage, FrontPageController) or a fully
 * qualified class name.
 *
 * ## EXAMPLES
 *
 *     wp capstan context FrontPage
 *     wp capstan context "BristolHealthPartners\Controllers\EventsController"
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
        $manifest = $reflection->getDefaultProperties()['context_getters'] ?? [];
        $template = $reflection->getDefaultProperties()['template'] ?? null;

        \WP_CLI::log("Controller: {$class}");
        \WP_CLI::log('Source:     ' . $reflection->getFileName());
        \WP_CLI::log('Extends:    ' . ($reflection->getParentClass()?->getName() ?? '—'));
        \WP_CLI::log('Template:   ' . ($template ?: '(inferred)'));
        \WP_CLI::log('');

        $rows = [];
        $mapped_methods = [];

        foreach ((array) $manifest as $key => $method) {
            if (is_int($key)) {
                [$key, $method] = [$method, "get_{$method}"];
            }

            $mapped_methods[] = $method;
            $rows[] = [
                'context key' => $key,
                'getter' => $method . ($reflection->hasMethod($method) ? '' : '  (MISSING)'),
                'declared in' => $reflection->hasMethod($method)
                    ? $this->declared_in($reflection->getMethod($method))
                    : '—',
            ];
        }

        if ($rows) {
            \WP_CLI::log('Context manifest ($context_getters):');
            \WP_CLI\Utils\format_items('table', $rows, ['context key', 'getter', 'declared in']);
        } else {
            \WP_CLI::log('Context manifest: (none — context comes from get_context() and parent controllers)');
        }

        $unmapped = [];
        $overrides = [];

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
                $overrides[] = $method->getName() . '()  — overrides ' . $parent;
            } else {
                $unmapped[] = $method->getName() . '()  — ' . $this->declared_in($method);
            }
        }

        if ($overrides) {
            \WP_CLI::log('');
            \WP_CLI::log('Framework getter overrides (feed the parent controller\'s context):');

            foreach ($overrides as $line) {
                \WP_CLI::log("  {$line}");
            }
        }

        if ($unmapped) {
            \WP_CLI::log('');
            \WP_CLI::log('Getters not in the manifest (available, but not auto-published):');

            foreach ($unmapped as $line) {
                \WP_CLI::log("  {$line}");
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
