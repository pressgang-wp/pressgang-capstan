<?php

namespace PressGang\Capstan\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class ThemePackager
{
    /** @var list<string> Directories to exclude from packaging. */
    private const EXCLUDED_DIRS = [
        '.claude',
        '.git',
        '.github',
        '.husky',
        '.idea',
        '.vscode',
        'node_modules',
        'release',
        'tests',
        'vendor',
    ];

    /** @var list<string> Filenames to exclude from packaging. */
    private const EXCLUDED_FILES = [
        // OS artifacts
        '.DS_Store',
        'Thumbs.db',
        // Git metadata
        '.gitignore',
        '.gitattributes',
        // Editor config
        '.editorconfig',
        // Composer
        'composer.lock',
        'composer.phar',
        // JS package managers
        'package.json',
        'package-lock.json',
        'yarn.lock',
        'pnpm-lock.yaml',
        // JS build tooling
        'webpack.config.js',
        'vite.config.js',
        'rollup.config.js',
        'gulpfile.js',
        'Gruntfile.js',
        'tsconfig.json',
        'postcss.config.js',
        'tailwind.config.js',
        'babel.config.js',
        'eslint.config.js',
        'prettier.config.js',
        'stylelint.config.js',
        // JS environment
        '.browserslistrc',
        '.nvmrc',
        '.node-version',
        '.prettierignore',
        '.eslintignore',
        // PHP dev tooling
        'phpunit.xml',
        'phpunit.xml.dist',
        'phpcs.xml',
        'phpcs.xml.dist',
        '.phpcs.xml',
        '.phpcs.xml.dist',
        'phpstan.neon',
        'phpstan.neon.dist',
        '.php-cs-fixer.php',
        '.php-cs-fixer.dist.php',
        // Container / CI
        'Dockerfile',
        'docker-compose.yml',
        'docker-compose.yaml',
        '.dockerignore',
        '.travis.yml',
        // AI agent guides
        'AGENTS.md',
        'CLAUDE.md',
    ];

    /** @var list<string> File extensions to exclude from packaging. */
    private const EXCLUDED_EXTENSIONS = [
        'bak',
        'swp',
        'swo',
        'tmp',
    ];

    /** @var list<string> Log file prefixes to exclude from packaging. */
    private const EXCLUDED_LOG_PREFIXES = [
        'npm-debug.log',
        'yarn-debug.log',
        'yarn-error.log',
        'pnpm-debug.log',
    ];

    /** @var list<string> Filename prefixes to exclude (matches the prefix plus any suffix). */
    private const EXCLUDED_FILENAME_PREFIXES = [
        '.env',
        '.eslintrc',
        '.stylelintrc',
        '.prettierrc',
        '.babelrc',
    ];

    /**
     * Validate that a directory is a valid theme directory.
     *
     * @param string $themeDir Absolute path to the theme directory.
     * @return array<string, string> Empty array on success, or ['error' => message] on failure.
     */
    public function validate(string $themeDir): array
    {
        if (! is_dir($themeDir)) {
            return ['error' => 'Directory does not exist: ' . $themeDir];
        }

        if (! file_exists($themeDir . '/style.css')) {
            return ['error' => 'Not a theme directory (missing style.css): ' . $themeDir];
        }

        return [];
    }

    /**
     * Derive the theme slug from the directory name.
     *
     * @param string $themeDir Absolute path to the theme directory.
     * @return string The theme slug (directory basename).
     */
    public function deriveSlug(string $themeDir): string
    {
        return basename($themeDir);
    }

    /**
     * Collect all files to include in the package, applying exclusion rules.
     *
     * @param string $themeDir Absolute path to the theme directory.
     * @return list<string> Sorted list of relative file paths.
     */
    public function collectFiles(string $themeDir): array
    {
        $themeDir = rtrim($themeDir, '/');
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($themeDir) + 1);

            if (! $this->isExcluded($relativePath)) {
                $files[] = $relativePath;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Create a zip archive from the collected files.
     *
     * @param string $themeDir Absolute path to the theme directory.
     * @param string $outputPath Absolute path for the output zip file.
     * @param list<string> $files Relative file paths to include.
     * @return int Number of files added to the archive.
     * @throws \RuntimeException If the zip archive cannot be created or written.
     */
    public function package(string $themeDir, string $outputPath, array $files): int
    {
        $outputDir = dirname($outputPath);

        if (! is_dir($outputDir)) {
            throw new \RuntimeException('Cannot create zip archive: ' . $outputPath);
        }

        $slug = $this->deriveSlug($themeDir);
        $themeDir = rtrim($themeDir, '/');

        $zip = new ZipArchive();
        $result = $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException('Cannot create zip archive: ' . $outputPath);
        }

        foreach ($files as $relativePath) {
            $added = $zip->addFile($themeDir . '/' . $relativePath, $slug . '/' . $relativePath);

            if (! $added) {
                $zip->close();
                throw new \RuntimeException('Failed to add file to archive: ' . $relativePath);
            }
        }

        if (! $zip->close()) {
            throw new \RuntimeException('Failed to write zip archive: ' . $outputPath);
        }

        return count($files);
    }

    /**
     * Check whether a relative path should be excluded from packaging.
     *
     * @param string $relativePath Path relative to the theme root.
     */
    private function isExcluded(string $relativePath): bool
    {
        $parts = explode('/', $relativePath);
        $filename = basename($relativePath);

        // Check excluded directories.
        foreach ($parts as $part) {
            if (in_array($part, self::EXCLUDED_DIRS, true)) {
                return true;
            }
        }

        // Check excluded filenames.
        if (in_array($filename, self::EXCLUDED_FILES, true)) {
            return true;
        }

        // Check excluded extensions.
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array($extension, self::EXCLUDED_EXTENSIONS, true)) {
            return true;
        }

        // Check excluded log prefixes.
        foreach (self::EXCLUDED_LOG_PREFIXES as $prefix) {
            if ($filename === $prefix || str_starts_with($filename, $prefix)) {
                return true;
            }
        }

        // Check excluded filename prefixes (e.g. .env, .eslintrc).
        foreach (self::EXCLUDED_FILENAME_PREFIXES as $prefix) {
            if ($filename === $prefix || str_starts_with($filename, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
