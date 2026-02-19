<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\ThemePackager;

/**
 * Create a WordPress-uploadable ZIP from a PressGang theme directory.
 *
 * Packages the theme directory into a zip that can be installed via
 * Appearance > Themes > Add New > Upload Theme in WordPress admin.
 *
 * ## OPTIONS
 *
 * [<path>]
 * : Theme directory to package. Defaults to the current working directory.
 *
 * [--output=<file>]
 * : Output zip file path. Defaults to <slug>.zip alongside the theme directory.
 *
 * [--force]
 * : Create the zip file. Without this flag, only a dry-run plan is shown.
 *
 * [--dry-run]
 * : Show the execution plan without creating the zip (default behaviour).
 *
 * ## EXAMPLES
 *
 *     # Preview what would be packaged
 *     wp capstan theme package
 *
 *     # Package the current directory
 *     wp capstan theme package --force
 *
 *     # Package a specific directory
 *     wp capstan theme package /path/to/my-theme --force
 *
 *     # Custom output path
 *     wp capstan theme package --output=release/my-theme.zip --force
 *
 * @when before_wp_load
 */
class ThemePackageCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $themeDir = isset($args[0]) ? rtrim($args[0], '/') : getcwd();
        $force = isset($assoc_args['force']);

        $packager = new ThemePackager();

        $errors = $packager->validate($themeDir);

        if ($errors) {
            \WP_CLI::error($errors['error']);
        }

        $slug = $packager->deriveSlug($themeDir);
        $outputPath = $assoc_args['output'] ?? dirname($themeDir) . '/' . $slug . '.zip';

        $files = $packager->collectFiles($themeDir);

        if (empty($files)) {
            \WP_CLI::error('No files found to package in: ' . $themeDir);
        }

        $this->printPlan($slug, $themeDir, $outputPath, $files);

        if ($force) {
            $outputDir = dirname($outputPath);

            if (! is_dir($outputDir)) {
                \WP_CLI::error('Output directory does not exist: ' . $outputDir);
            }

            $count = $packager->package($themeDir, $outputPath, $files);
            $bytes = filesize($outputPath);
            $size = $bytes !== false ? $this->humanFileSize($bytes) : 'unknown size';

            \WP_CLI::log('');
            \WP_CLI::success(sprintf('%d files packaged into %s (%s)', $count, $outputPath, $size));
        } else {
            \WP_CLI::log('');
            \WP_CLI::log('Dry run complete. Re-run with --force to create the zip.');
        }
    }

    /**
     * Print the packaging plan.
     *
     * @param string $slug Theme slug.
     * @param string $themeDir Theme directory path.
     * @param string $outputPath Output zip path.
     * @param list<string> $files Files to include.
     */
    private function printPlan(string $slug, string $themeDir, string $outputPath, array $files): void
    {
        \WP_CLI::log('theme package — execution plan');
        \WP_CLI::log('');
        \WP_CLI::log('Slug:     ' . $slug);
        \WP_CLI::log('Source:   ' . $themeDir);
        \WP_CLI::log('Output:   ' . $outputPath);
        \WP_CLI::log('');
        \WP_CLI::log('Files (' . count($files) . '):');

        foreach ($files as $relativePath) {
            \WP_CLI::log('  ' . $slug . '/' . $relativePath);
        }
    }

    /**
     * Format a byte count as a human-readable file size.
     *
     * @param int $bytes File size in bytes.
     * @return string Formatted size string (e.g. "1.23 MB").
     */
    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = 0;

        while ($bytes >= 1024 && $factor < count($units) - 1) {
            $bytes /= 1024;
            $factor++;
        }

        return round($bytes, 2) . ' ' . $units[$factor];
    }
}
