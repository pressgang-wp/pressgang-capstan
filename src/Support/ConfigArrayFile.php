<?php

namespace PressGang\Capstan\Support;

/**
 * Reads and appends entries in PressGang config files — plain PHP files
 * that return an array.
 *
 * Insertion is deliberately conservative: an entry is only ever appended
 * immediately before the file's closing `];`, and if the file doesn't end
 * in that recognisable shape, nothing is written and the caller falls back
 * to printing the snippet for manual pasting. Config files are data, and a
 * generator that half-understands one shouldn't edit it.
 */
final class ConfigArrayFile
{
    /**
     * The array a config file returns, or null when the file is missing or
     * doesn't return an array.
     *
     * @return array<array-key, mixed>|null
     */
    public static function read(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $value = include $file;

        return is_array($value) ? $value : null;
    }

    /**
     * Appends an entry (pre-rendered PHP source, without trailing newline)
     * before the file's closing `];`.
     *
     * @param string $file  Config file path.
     * @param string $entry Entry source, e.g. "\t'event' => [ ... ],".
     * @return bool Whether the file was written.
     */
    public static function append(string $file, string $entry): bool
    {
        $source = (string) file_get_contents($file);

        $updated = preg_replace(
            '/\n\];\s*$/',
            "\n{$entry}\n];\n",
            $source,
            1,
            $count
        );

        if ($count !== 1) {
            return false;
        }

        return file_put_contents($file, $updated) !== false;
    }

    /**
     * Creates a config file returning an array with one entry.
     *
     * @param string $file   Config file path.
     * @param string $header Doc header lines (without comment markers).
     * @param string $entry  Entry source.
     * @return bool Whether the file was written.
     */
    public static function create(string $file, string $header, string $entry): bool
    {
        $comment = implode("\n", array_map(
            fn (string $line) => rtrim(" * {$line}"),
            explode("\n", $header)
        ));

        $source = "<?php\n\n/**\n{$comment}\n */\n\nreturn [\n{$entry}\n];\n";

        return file_put_contents($file, $source) !== false;
    }
}
