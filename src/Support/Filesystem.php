<?php

declare(strict_types=1);

namespace PressGang\Capstan\Support;

use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class Filesystem
{
    private SymfonyFilesystem $fs;

    public function __construct()
    {
        $this->fs = new SymfonyFilesystem();
    }

    public function copyDirectory(string $source, string $destination): void
    {
        $this->fs->mirror($source, $destination);
    }

    public function ensureDirectory(string $path): void
    {
        $this->fs->mkdir($path);
    }

    public function fileExists(string $path): bool
    {
        return $this->fs->exists($path);
    }
}
