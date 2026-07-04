<?php

namespace PressGang\Capstan\Commands;

/**
 * Lists the snippets registered in config/snippets.php, showing how each
 * resolves (theme, parent framework, or snippets library) and whether the
 * class actually exists — using the same ClassResolver the framework uses
 * at boot.
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format.
 * ---
 * default: table
 * options:
 *   - table
 *   - json
 *   - csv
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan snippets
 *     wp capstan snippets --format=json
 */
class SnippetsCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\Bootstrap\Config::class)) {
            \WP_CLI::error('PressGang 2 is not loaded — is a PressGang child theme active?');
        }

        $snippets = (array) \PressGang\Bootstrap\Config::get('snippets', []);

        if ($snippets === []) {
            \WP_CLI::log('No snippets registered (config/snippets.php).');

            return;
        }

        $namespace = \get_child_theme_namespace();
        $rows = [];

        foreach ($snippets as $snippet => $snippet_args) {
            $resolved = \PressGang\Util\ClassResolver::resolve($snippet, 'Snippets', $namespace);

            $rows[] = [
                'snippet' => $snippet,
                'resolves to' => $resolved ?? '(UNRESOLVED)',
                'source' => $resolved ? $this->source($resolved, $namespace) : '—',
                'args' => $snippet_args ? (string) json_encode($snippet_args, JSON_UNESCAPED_SLASHES) : '',
            ];
        }

        \WP_CLI\Utils\format_items($assoc_args['format'] ?? 'table', $rows, ['snippet', 'resolves to', 'source', 'args']);

        if ($unresolved = array_filter($rows, fn (array $row) => $row['resolves to'] === '(UNRESOLVED)')) {
            \WP_CLI::warning(count($unresolved) . ' snippet(s) do not resolve to a class and are silently skipped at boot.');
        }
    }

    /**
     * A human label for where a resolved snippet class lives.
     */
    private function source(string $class, ?string $namespace): string
    {
        return match (true) {
            $namespace !== null && str_starts_with($class, "{$namespace}\\") => 'theme',
            str_starts_with($class, 'PressGang\\Snippets\\') => 'library',
            str_starts_with($class, 'PressGang\\') => 'framework',
            default => 'other',
        };
    }
}
