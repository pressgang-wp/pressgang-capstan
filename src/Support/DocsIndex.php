<?php

namespace PressGang\Capstan\Support;

/**
 * Searches the machine-readable API indexes shipped by installed PressGang
 * packages (`vendor/{org}/{pkg}/docs/api-index.json`).
 *
 * Reading straight from vendor makes results version-accurate by construction:
 * the installed files are exactly what composer.lock pinned, so a search
 * answers for *this* theme's versions with no network call. This is what turns
 * the static local indexes into a queryable, version-aware documentation tool
 * (the `pressgang_docs_search` MCP tool).
 *
 * Missing, oversized, or malformed indexes are silently skipped — an index is
 * an enhancement, never a requirement.
 */
final class DocsIndex
{
    /** Indexes larger than this are skipped rather than searched. */
    public const MAX_BYTES = 262144;

    /** @var list<string> Absolute roots whose vendor/ dirs are scanned. */
    private array $roots;

    /**
     * @param list<string>|null $roots Defaults to the active child and parent
     *                                 theme directories (needs WordPress).
     */
    public function __construct(?array $roots = null)
    {
        $this->roots = $roots ?? self::default_roots();
    }

    /** @return list<string> */
    private static function default_roots(): array
    {
        $roots = [];

        if (function_exists('get_stylesheet_directory')) {
            $roots[] = \get_stylesheet_directory();
        }

        if (function_exists('get_template_directory')) {
            $roots[] = \get_template_directory();
        }

        return array_values(array_unique(array_filter($roots)));
    }

    /**
     * Every valid index discovered under the roots, keyed by package name.
     *
     * @return array<string, array>
     */
    public function indexes(): array
    {
        $found = [];

        foreach ($this->roots as $root) {
            foreach (glob("{$root}/vendor/*/*/docs/api-index.json") ?: [] as $file) {
                if (! is_file($file) || filesize($file) > self::MAX_BYTES) {
                    continue;
                }

                $index = json_decode((string) file_get_contents($file), true);

                if (! is_array($index) || ! isset($index['methods']) || ! is_array($index['methods'])) {
                    continue;
                }

                $found[(string) ($index['package'] ?? $file)] = $index;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Search installed indexes, ranked most relevant first.
     *
     * @param string      $query   Free text matched against method name,
     *                             signature, args, notes, and group.
     * @param string|null $package Optional case-insensitive package filter.
     * @param int         $limit   Maximum hits to return.
     *
     * @return array{query: string, packages: list<string>, results: list<array>}
     */
    public function search(string $query, ?string $package = null, int $limit = 15): array
    {
        $q = strtolower(trim($query));
        $results = [];
        $packages = [];

        foreach ($this->indexes() as $pkg => $index) {
            if ($package !== null && ! str_contains(strtolower($pkg), strtolower($package))) {
                continue;
            }

            $packages[] = $pkg . '@' . ($index['version'] ?? '?');

            foreach ($index['methods'] as $method) {
                $score = $this->score($q, $method);

                if ($score === 0) {
                    continue;
                }

                $results[] = [
                    'score'     => $score,
                    'package'   => $pkg,
                    'version'   => $index['version'] ?? null,
                    'name'      => $method['name'] ?? null,
                    'signature' => $method['signature'] ?? null,
                    'group'     => $method['group'] ?? null,
                    'sets_args' => $method['sets_args'] ?? [],
                    'wp_docs'   => $method['wp_docs'] ?? [],
                    'notes'     => $method['notes'] ?? null,
                ];
            }
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);

        foreach ($results as &$result) {
            unset($result['score']);
        }

        return ['query' => $query, 'packages' => $packages, 'results' => array_values($results)];
    }

    /**
     * Relevance of a query against one method entry (0 = no match). Name hits
     * outrank signature/arg hits, which outrank notes/group mentions.
     */
    private function score(string $q, array $method): int
    {
        if ($q === '') {
            return 0;
        }

        $name = strtolower((string) ($method['name'] ?? ''));
        $signature = strtolower((string) ($method['signature'] ?? ''));
        $notes = strtolower((string) ($method['notes'] ?? ''));
        $group = strtolower((string) ($method['group'] ?? ''));
        $args = strtolower(implode(' ', (array) ($method['sets_args'] ?? [])));

        $score = 0;

        if ($name === $q) {
            $score += 100;
        } elseif ($name !== '' && str_starts_with($name, $q)) {
            $score += 60;
        } elseif ($name !== '' && str_contains($name, $q)) {
            $score += 40;
        }

        if (str_contains($signature, $q)) {
            $score += 15;
        }

        if ($args !== '' && str_contains($args, $q)) {
            $score += 12;
        }

        if (str_contains($notes, $q)) {
            $score += 8;
        }

        if (str_contains($group, $q)) {
            $score += 5;
        }

        return $score;
    }
}
