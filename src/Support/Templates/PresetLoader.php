<?php

namespace PressGang\Capstan\Support\Templates;

class PresetLoader
{
    /**
     * Load a preset from a directory containing manifest.json and theme/.
     *
     * @throws \RuntimeException If manifest.json is missing or malformed.
     */
    public static function load(string $presetDir): Preset
    {
        $manifestPath = $presetDir . '/manifest.json';

        if (! file_exists($manifestPath)) {
            throw new \RuntimeException("Preset manifest not found: {$manifestPath}");
        }

        $raw = file_get_contents($manifestPath);
        $manifest = json_decode($raw, true);

        if (! is_array($manifest)) {
            throw new \RuntimeException("Invalid manifest JSON: {$manifestPath}");
        }

        if (! isset($manifest['text_extensions']) || ! is_array($manifest['text_extensions'])) {
            throw new \RuntimeException("Manifest missing 'text_extensions' array: {$manifestPath}");
        }

        return new Preset(
            basePath: $presetDir,
            textExtensions: $manifest['text_extensions'],
            renameMap: $manifest['rename_map'] ?? [],
            defaults: $manifest['defaults'] ?? [],
        );
    }

    /**
     * Resolve the absolute path to a built-in template type.
     */
    public static function resolveBuiltinPath(string $type): string
    {
        return dirname(__DIR__, 3) . '/templates/' . $type;
    }
}
