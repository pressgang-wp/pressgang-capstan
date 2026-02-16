<?php

declare(strict_types=1);

namespace PressGang\Capstan\Support\Templates;

use PressGang\Capstan\Support\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TemplateApplier
{
    public function __construct(
        private Preset $preset,
        private Filesystem $filesystem,
    ) {}

    /**
     * Generate a plan of file operations without writing anything.
     *
     * @param array<string, string> $tokens Token name => replacement value.
     * @param string $targetDir Absolute path to the target directory.
     * @return array<int, array{source: string, target: string, relativePath: string, tokenized: bool}>
     */
    public function plan(array $tokens, string $targetDir): array
    {
        $themeDir = $this->preset->themeDir();
        $plan = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $sourcePath = $file->getPathname();
            $relativePath = substr($sourcePath, strlen($themeDir) + 1);

            $targetRelative = $this->applyRenameMap($relativePath, $tokens);
            $targetPath = $targetDir . '/' . $targetRelative;

            $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
            $tokenized = in_array($extension, $this->preset->textExtensions, true);

            $plan[] = [
                'source' => $sourcePath,
                'target' => $targetPath,
                'relativePath' => $targetRelative,
                'tokenized' => $tokenized,
            ];
        }

        usort($plan, fn (array $a, array $b) => strcmp($a['relativePath'], $b['relativePath']));

        return $plan;
    }

    /**
     * Write files to disk according to the plan.
     *
     * @param array<string, string> $tokens Token name => replacement value.
     * @param string $targetDir Absolute path to the target directory.
     * @param bool $force Whether to overwrite existing files.
     * @return array<int, string> List of written file paths (relative to target).
     */
    public function apply(array $tokens, string $targetDir, bool $force = false): array
    {
        $plan = $this->plan($tokens, $targetDir);
        $written = [];

        foreach ($plan as $entry) {
            if ($this->filesystem->fileExists($entry['target']) && ! $force) {
                continue;
            }

            $this->filesystem->ensureDirectory(dirname($entry['target']));

            if ($entry['tokenized']) {
                $content = $this->filesystem->readFile($entry['source']);
                $content = $this->replaceTokens($content, $tokens);
                $this->filesystem->writeFile($entry['target'], $content);
            } else {
                $this->filesystem->copy($entry['source'], $entry['target']);
            }

            $written[] = $entry['relativePath'];
        }

        return $written;
    }

    /**
     * Replace all {{token}} placeholders in a string.
     *
     * @param string $content The content to process.
     * @param array<string, string> $tokens Token name => replacement value.
     */
    public function replaceTokens(string $content, array $tokens): string
    {
        $search = [];
        $replace = [];

        foreach ($tokens as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $content);
    }

    private function applyRenameMap(string $relativePath, array $tokens): string
    {
        if (isset($this->preset->renameMap[$relativePath])) {
            $mapped = $this->preset->renameMap[$relativePath];
            return $this->replaceTokens($mapped, $tokens);
        }

        return $relativePath;
    }
}
