<?php

namespace PressGang\Capstan\Support;

/**
 * Generates a standard `docs/api-index.json` for a package by reflecting its
 * public API. The schema is the one Quartermaster established; this engine is
 * the shared, package-agnostic core of it, so every package emits the same
 * shape and {@see DocsIndex} / `pressgang_docs_search` can read them uniformly.
 *
 * What is generic lives here: signature rendering, and docblock parsing for the
 * one-line note, WordPress/Timber doc links, and any `Sets:` args annotation.
 * What is package-specific — which classes and methods, in which groups, and
 * which read request globals — is supplied as a manifest (an `api-index.php`
 * returning an array), never hard-coded here.
 */
final class ApiIndexGenerator
{
    /**
     * Build the index payload from a manifest.
     *
     * @param array $manifest {
     *     package:       string,
     *     version:       string,
     *     entrypoint:    class-string|string,
     *     principles:    list<string>,
     *     reads_globals: array<string, bool>,           // method name => true
     *     groups:        array<string, array{0: class-string, 1: list<string>}>
     *                    // group label => [class, [method, ...]]
     * }
     * @param string $generated_at ISO 8601 UTC timestamp (passed in so the
     *                             engine stays pure and testable).
     *
     * @return array
     */
    public function generate(array $manifest, string $generated_at): array
    {
        $reads_globals = $manifest['reads_globals'] ?? [];
        $methods = [];

        foreach ($manifest['groups'] ?? [] as $group => [$class, $names]) {
            $ref = new \ReflectionClass($class);

            foreach ($names as $name) {
                if (! $ref->hasMethod($name)) {
                    continue;
                }

                $method = $ref->getMethod($name);

                if (! $method->isPublic()) {
                    continue;
                }

                $meta = $this->docblock($method);

                $methods[] = [
                    'name' => $method->getName(),
                    'signature' => $this->signature($method),
                    'group' => $group,
                    'sets_args' => $meta['sets_args'],
                    'reads_globals' => $reads_globals[$method->getName()] ?? false,
                    'wp_docs' => $meta['wp_docs'],
                    'notes' => $meta['notes'],
                ];
            }
        }

        return [
            'package' => $manifest['package'],
            'version' => $manifest['version'],
            'generated_at' => $generated_at,
            'entrypoint' => ltrim((string) ($manifest['entrypoint'] ?? ''), '\\'),
            'principles' => array_values($manifest['principles'] ?? []),
            'methods' => $methods,
        ];
    }

    /** Render a full `name(params): return` signature. */
    public function signature(\ReflectionMethod $method): string
    {
        $params = array_map([$this, 'render_parameter'], $method->getParameters());

        return $method->getName()
            . '(' . implode(', ', $params) . ')'
            . ': ' . $this->render_type($method->getReturnType());
    }

    private function render_parameter(\ReflectionParameter $parameter): string
    {
        $rendered = $this->render_type($parameter->getType())
            . ' ' . ($parameter->isVariadic() ? '...' : '')
            . ($parameter->isPassedByReference() ? '&' : '')
            . '$' . $parameter->getName();

        if ($parameter->isDefaultValueAvailable() && ! $parameter->isVariadic()) {
            $default = $parameter->isDefaultValueConstant()
                ? (string) $parameter->getDefaultValueConstantName()
                : str_replace("\n", '', var_export($parameter->getDefaultValue(), true));

            $rendered .= ' = ' . $default;
        }

        return $rendered;
    }

    private function render_type(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();

            if ($name === 'null') {
                return 'null';
            }

            return $type->allowsNull() && $name !== 'mixed' ? $name . '|null' : $name;
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map([$this, 'render_type'], $type->getTypes()));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map([$this, 'render_type'], $type->getTypes()));
        }

        return 'mixed';
    }

    /**
     * Parse the one-line note, doc links, and any `Sets:` args from a docblock.
     *
     * @return array{sets_args: list<string>, notes: string, wp_docs: list<string>}
     */
    private function docblock(\ReflectionMethod $method): array
    {
        $doc = (string) $method->getDocComment();

        $sets_args = [];
        $has_sets_line = false;

        if (preg_match('/Sets:\s*(.+)/i', $doc, $match) === 1) {
            $has_sets_line = true;
            $raw = trim($match[1]);

            if ($raw !== '' && stripos($raw, '(none)') === false && stripos($raw, '(dynamic)') === false) {
                $sets_args = array_values(array_filter(array_map('trim', explode(',', $raw))));
            }
        }

        preg_match_all('/https:\/\/developer\.wordpress\.org\/[^\s*]+|https:\/\/timber\.github\.io\/[^\s*]+/i', $doc, $urls);
        $wp_docs = array_values(array_unique($urls[0] ?? []));

        $notes = '(args mapping not annotated yet)';

        foreach (preg_split('/\R/', $doc) as $line) {
            $line = ltrim(trim($line), "/* \t");

            if ($line === '' || str_starts_with($line, '@') || str_starts_with($line, 'Sets:') || str_starts_with($line, 'See:')) {
                continue;
            }

            $notes = $line;
            break;
        }

        if ($sets_args === [] && ! $has_sets_line && $notes !== '(args mapping not annotated yet)') {
            $notes .= ' (args mapping not annotated yet)';
        }

        return ['sets_args' => $sets_args, 'notes' => $notes, 'wp_docs' => $wp_docs];
    }
}
