<?php

namespace PressGang\Capstan\Support;

/**
 * Reads and rewrites a controller's $context_getters manifest in source.
 *
 * Same contract as ConfigArrayFile, but for one property inside a class:
 * the array literal is only rewritten when it contains nothing but quoted
 * string entries ('key' or 'key' => 'method'), and a missing property is
 * only inserted at a recognisable point (after the trait use block, or the
 * class opening brace). Anything unusual — comments inside the array,
 * computed values, unrecognisable class shape — returns null and the
 * caller prints the snippet instead of editing code it half-understands.
 */
final class ManifestWriter
{
    private const PROPERTY = '/(protected\s+array\s+\$context_getters\s*=\s*\[)([^\]]*)(\]\s*;)/';

    private const ENTRY = "/'([^']+)'(?:\s*=>\s*'([^']+)')?/";

    /**
     * The manifest entries declared in source, or null when the property is
     * absent or the array literal isn't a plain list of quoted entries.
     *
     * @return array<int|string, string>|null List values and 'key' => 'method' pairs.
     */
    public static function keys(string $source): ?array
    {
        if (! preg_match(self::PROPERTY, $source, $match)) {
            return null;
        }

        return self::parse_literal($match[2]);
    }

    /**
     * Whether the property is declared at all (parseable or not).
     */
    public static function has_property(string $source): bool
    {
        return (bool) preg_match('/\$context_getters\s*=/', $source);
    }

    /**
     * Source with the given keys appended to the existing manifest, or null
     * when the property (or the whole class) isn't in a recognisable shape.
     *
     * @param string             $source Class source.
     * @param array<int, string> $keys   Plain context keys to append.
     */
    public static function add(string $source, array $keys): ?string
    {
        if (! self::has_property($source)) {
            return self::insert($source, $keys);
        }

        if (! preg_match(self::PROPERTY, $source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $entries = self::parse_literal($match[2][0]);

        if ($entries === null) {
            return null;
        }

        $appended = false;

        foreach ($keys as $key) {
            if (! in_array($key, $entries, true) && ! array_key_exists($key, $entries)) {
                $entries[] = $key;
                $appended = true;
            }
        }

        // Nothing new: leave the source byte-for-byte alone rather than
        // churning the author's formatting with a re-render.
        if (! $appended) {
            return $source;
        }

        return substr($source, 0, (int) $match[1][1])
            . rtrim($match[1][0], '[') . self::render($entries) . ';'
            . substr($source, (int) $match[3][1] + strlen($match[3][0]));
    }

    /**
     * Source with a new documented manifest property inserted after the
     * trait use block (or the class opening brace), or null when neither
     * anchor is found.
     *
     * @param string             $source Class source.
     * @param array<int, string> $keys   Plain context keys.
     */
    public static function insert(string $source, array $keys): ?string
    {
        $property = "\n\t/**\n"
            . "\t * Context keys published to the template, each populated from its\n"
            . "\t * get_{key}() getter.\n"
            . "\t *\n"
            . "\t * @var array<int|string, string>\n"
            . "\t */\n"
            . "\tprotected array \$context_getters = " . self::render(array_values($keys)) . ";\n";

        // After the last class-level trait use (indented, unlike imports).
        if (preg_match_all('/^[\t ]+use\s+[^;(]+;[\t ]*$/m', $source, $uses, PREG_OFFSET_CAPTURE) && $uses[0]) {
            $last = end($uses[0]);
            $at = (int) $last[1] + strlen($last[0]);

            return substr($source, 0, $at) . "\n" . $property . substr($source, $at);
        }

        // Otherwise directly after the class opening brace.
        if (preg_match('/^(?:final\s+|abstract\s+)?class\s+\w+[^{]*\{/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
            $at = (int) $match[0][1] + strlen($match[0][0]);

            return substr($source, 0, $at) . "\n" . $property . substr($source, $at);
        }

        return null;
    }

    /**
     * Parses a flat array literal of quoted entries, or null when it
     * contains anything else.
     *
     * @return array<int|string, string>|null
     */
    private static function parse_literal(string $literal): ?array
    {
        // Anything left after removing entries, commas, and whitespace means
        // the literal holds something we don't understand.
        if (trim((string) preg_replace([self::ENTRY, '/[\s,]+/'], '', $literal)) !== '') {
            return null;
        }

        preg_match_all(self::ENTRY, $literal, $matches, PREG_SET_ORDER);

        $entries = [];

        foreach ($matches as $match) {
            if (isset($match[2])) {
                $entries[$match[1]] = $match[2];
            } else {
                $entries[] = $match[1];
            }
        }

        return $entries;
    }

    /**
     * Renders entries as an array literal — single line while it stays
     * readable, one entry per line beyond that.
     *
     * @param array<int|string, string> $entries
     */
    private static function render(array $entries): string
    {
        $rendered = [];

        foreach ($entries as $key => $value) {
            $rendered[] = is_int($key) ? "'{$value}'" : "'{$key}' => '{$value}'";
        }

        if ($rendered === []) {
            return '[]';
        }

        $single = '[ ' . implode(', ', $rendered) . ' ]';

        // "\tprotected array $context_getters = " occupies ~40 columns.
        if (strlen($single) <= 60) {
            return $single;
        }

        return "[\n\t\t" . implode(",\n\t\t", $rendered) . ",\n\t]";
    }
}
