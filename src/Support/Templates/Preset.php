<?php

namespace PressGang\Capstan\Support\Templates;

final readonly class Preset
{
    /**
     * @param string $basePath Absolute path to the preset directory.
     * @param array<int, string> $textExtensions File extensions eligible for token replacement.
     * @param array<string, string> $renameMap Source-relative path => target-relative path (may contain tokens).
     * @param array<string, string> $defaults Fallback token values.
     */
    public function __construct(
        public string $basePath,
        public array $textExtensions,
        public array $renameMap,
        public array $defaults,
    ) {}

    public function themeDir(): string
    {
        return $this->basePath . '/theme';
    }
}
