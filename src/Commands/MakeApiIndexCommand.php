<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\ApiIndexGenerator;

/**
 * Generate a standard docs/api-index.json for a package from its api-index.php
 * manifest — the machine-readable API index agents read (and pressgang_docs_search
 * queries). One generator, one schema, every package.
 *
 * Run inside the package directory. The package ships an `api-index.php` returning
 * the manifest (package, version, entrypoint, principles, groups, reads_globals);
 * this command reflects the listed classes and writes docs/api-index.json.
 *
 * ## OPTIONS
 *
 * [<path>]
 * : Package directory. Default: the current working directory.
 *
 * [--force]
 * : Write the file (omit to preview the method count and any change).
 *
 * ## EXAMPLES
 *
 *     wp capstan make api-index                 # preview, from the package root
 *     wp capstan make api-index --force         # write docs/api-index.json
 *     wp capstan make api-index /path/to/pkg --force
 *
 * @when before_wp_load
 */
class MakeApiIndexCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $dir = rtrim($args[0] ?? (string) getcwd(), '/');
        $manifest_file = "{$dir}/api-index.php";

        if (! is_file($manifest_file)) {
            \WP_CLI::error("No api-index.php manifest in {$dir} — add one describing the package's API surface.");
        }

        // Load the package's own classes so the manifest's entries are reflectable.
        if (is_file("{$dir}/vendor/autoload.php")) {
            require_once "{$dir}/vendor/autoload.php";
        }

        $manifest = require $manifest_file;

        if (! is_array($manifest) || ! isset($manifest['groups'])) {
            \WP_CLI::error('api-index.php must return an array with at least package, version, and groups.');
        }

        $payload = (new ApiIndexGenerator())->generate($manifest, gmdate('Y-m-d\TH:i:s\Z'));
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        $target = "{$dir}/docs/api-index.json";
        $count = count($payload['methods']);

        \WP_CLI::log("Package:    {$payload['package']} {$payload['version']}");
        \WP_CLI::log("Entrypoint: {$payload['entrypoint']}");
        \WP_CLI::log("Methods:    {$count} across " . count($manifest['groups']) . ' groups');

        if (is_file($target) && (string) file_get_contents($target) === $json) {
            \WP_CLI::success("{$target} is already up to date.");

            return;
        }

        if (! isset($assoc_args['force'])) {
            \WP_CLI::log('');
            \WP_CLI::log("Dry run — re-run with --force to write {$target}.");

            return;
        }

        if (! is_dir("{$dir}/docs")) {
            mkdir("{$dir}/docs", 0755, true);
        }

        file_put_contents($target, $json);

        \WP_CLI::success("Wrote {$target} ({$count} methods).");
    }
}
