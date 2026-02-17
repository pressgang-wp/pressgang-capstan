<?php

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

    public function readFile(string $path): string
    {
        return file_get_contents($path);
    }

    public function writeFile(string $path, string $content): void
    {
        $this->fs->dumpFile($path, $content);
    }

    public function copy(string $source, string $destination): void
    {
        $this->fs->copy($source, $destination, true);
    }
}
