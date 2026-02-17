<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\ContextResolver;
use PressGang\Capstan\Support\Filesystem;
use PressGang\Capstan\Support\Templates\PresetLoader;
use PressGang\Capstan\Support\Templates\TemplateApplier;
use PressGang\Capstan\Support\Tokens;

/**
 * Scaffold a new PressGang child theme.
 *
 * ## OPTIONS
 *
 * <slug>
 * : Theme slug in kebab-case (e.g. my-theme).
 *
 * [--name=<name>]
 * : Human-readable theme name. Defaults to title-cased slug.
 *
 * [--namespace=<namespace>]
 * : PHP namespace for the theme. Defaults to PascalCase slug.
 *
 * [--path=<path>]
 * : Target themes directory. Defaults to detected WP themes dir.
 *
 * [--force]
 * : Write files to disk. Without this flag, only a dry-run plan is shown.
 *
 * [--dry-run]
 * : Show the execution plan without writing files (default behaviour).
 *
 * ## EXAMPLES
 *
 *     # Preview what would be generated
 *     wp capstan make child my-theme
 *
 *     # Generate the theme
 *     wp capstan make child my-theme --force
 *
 *     # Custom name and namespace
 *     wp capstan make child my-theme --name="My Theme" --namespace=MyTheme --force
 *
 *     # Explicit target path
 *     wp capstan make child my-theme --path=/srv/www/wp-content/themes --force
 *
 * @when before_wp_load
 */
class MakeChildCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $slug = $args[0];
        $this->validateSlug($slug);
        $this->validateNamespace($assoc_args);

        $force = isset($assoc_args['force']);

        $targetDir = $this->resolveTargetDir($slug, $assoc_args);
        $this->checkTargetDir($targetDir, $force);

        $templateDir = PresetLoader::resolveBuiltinPath('child');
        $presetObj = $this->loadPreset($templateDir);

        $tokens = $this->buildTokens($slug, $assoc_args, $presetObj);

        $filesystem = new Filesystem();
        $applier = new TemplateApplier($presetObj, $filesystem);
        $plan = $applier->plan($tokens, $targetDir);

        $this->printPlan($tokens, $targetDir, $plan);

        if ($force) {
            $written = $applier->apply($tokens, $targetDir, $force);
            \WP_CLI::log('');
            \WP_CLI::success(count($written) . ' files written to ' . $targetDir);
        } else {
            \WP_CLI::log('');
            \WP_CLI::log('Dry run complete. Re-run with --force to write files.');
        }
    }

    private function validateSlug(string $slug): void
    {
        if (! Tokens::isValidSlug($slug)) {
            \WP_CLI::error(
                'Invalid slug "' . $slug . '": must be kebab-case '
                . '(lowercase letters, numbers, and hyphens; no leading/trailing hyphens).'
            );
        }
    }

    /**
     * Validate that --namespace, if provided, is a legal PHP namespace.
     *
     * @param array<string, string> $assoc_args Named arguments from WP-CLI.
     * @return void
     */
    private function validateNamespace(array $assoc_args): void
    {
        if (! isset($assoc_args['namespace'])) {
            return;
        }

        if (! Tokens::isValidNamespace($assoc_args['namespace'])) {
            \WP_CLI::error(
                'Invalid namespace "' . $assoc_args['namespace'] . '": '
                . 'must be a valid PHP namespace (e.g. MyTheme or Acme\\MyTheme).'
            );
        }
    }

    private function resolveTargetDir(string $slug, array $assoc_args): string
    {
        if (isset($assoc_args['path'])) {
            return rtrim($assoc_args['path'], '/') . '/' . $slug;
        }

        $context = ContextResolver::resolve(getcwd());

        if ($context->themesDir === null) {
            \WP_CLI::error(
                'Could not detect a WordPress themes directory. '
                . 'Run this command from within a WordPress project, or pass --path explicitly.'
            );
        }

        return $context->themesDir . '/' . $slug;
    }

    private function checkTargetDir(string $targetDir, bool $force): void
    {
        if (is_dir($targetDir) && ! $force) {
            \WP_CLI::error(
                'Target directory already exists: ' . $targetDir . '. '
                . 'Use --force to overwrite.'
            );
        }
    }

    private function loadPreset(string $presetDir): \PressGang\Capstan\Support\Templates\Preset
    {
        try {
            return PresetLoader::load($presetDir);
        } catch (\RuntimeException $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildTokens(
        string $slug,
        array $assoc_args,
        \PressGang\Capstan\Support\Templates\Preset $preset,
    ): array {
        $tokens = $preset->defaults;

        $tokens['theme_slug'] = $slug;
        $tokens['theme_name'] = $assoc_args['name'] ?? Tokens::deriveThemeNameFromSlug($slug);
        $tokens['theme_namespace'] = $assoc_args['namespace'] ?? Tokens::deriveNamespaceFromSlug($slug);
        $tokens['textdomain'] = $slug;

        return $tokens;
    }

    private function printPlan(array $tokens, string $targetDir, array $plan): void
    {
        \WP_CLI::log('make child — execution plan');
        \WP_CLI::log('');
        \WP_CLI::log('Slug:       ' . $tokens['theme_slug']);
        \WP_CLI::log('Name:       ' . $tokens['theme_name']);
        \WP_CLI::log('Namespace:  ' . $tokens['theme_namespace']);
        \WP_CLI::log('Target:     ' . $targetDir);
        \WP_CLI::log('');
        \WP_CLI::log('Files (' . count($plan) . '):');

        foreach ($plan as $entry) {
            $marker = $entry['tokenized'] ? ' [T]' : '    ';
            $exists = file_exists($entry['target']) ? ' (overwrite)' : '';
            \WP_CLI::log('  ' . $marker . ' ' . $entry['relativePath'] . $exists);
        }
    }
}
