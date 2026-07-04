<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\ConfigArrayFile;

/**
 * Scaffolds a PressGang ACF block: block.json, a Twig template, and the
 * config/blocks.php registration.
 *
 * The block renders through PressGang\Blocks\Block, which builds a Timber
 * context (ACF field values as top-level variables, plus classes / styles /
 * anchor) and renders views/blocks/{slug}.twig. Dry-run by default.
 *
 * ## OPTIONS
 *
 * <slug>
 * : Block slug (lowercase kebab-case, e.g. hero, call-to-action).
 *
 * [--title=<title>]
 * : Block title shown in the editor. Defaults to the slug, title-cased.
 *
 * [--description=<description>]
 * : Block description.
 *
 * [--icon=<dashicon>]
 * : Editor icon (dashicon name without the prefix).
 * ---
 * default: block-default
 * ---
 *
 * [--force]
 * : Write the files (omit to preview).
 *
 * ## EXAMPLES
 *
 *     wp capstan make block hero --title=Hero
 *     wp capstan make block call-to-action --force
 */
class MakeBlockCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $slug = $args[0];

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            \WP_CLI::error("Invalid block slug '{$slug}' — lowercase kebab-case only.");
        }

        $theme = get_stylesheet_directory();
        $title = $assoc_args['title'] ?? ucwords(str_replace('-', ' ', $slug));

        $files = [
            "blocks/{$slug}/block.json" => $this->block_json($slug, $title, $assoc_args),
            "views/blocks/{$slug}.twig" => $this->twig($slug, $title),
        ];

        foreach (array_keys($files) as $relative) {
            if (is_file("{$theme}/{$relative}")) {
                \WP_CLI::error("{$relative} already exists.");
            }
        }

        $config = "{$theme}/config/blocks.php";
        $registered = ConfigArrayFile::read($config) ?? [];
        $needs_entry = ! in_array("/blocks/{$slug}", $registered, true)
            && ! array_key_exists("/blocks/{$slug}", $registered);

        foreach ($files as $relative => $content) {
            \WP_CLI::log("Would create: {$relative}");
        }

        if ($needs_entry) {
            \WP_CLI::log('Would register: ' . "'/blocks/{$slug}'" . ' in config/blocks.php');
        }

        if (! isset($assoc_args['force'])) {
            \WP_CLI::log('');
            \WP_CLI::log('Dry run — re-run with --force to write.');

            return;
        }

        foreach ($files as $relative => $content) {
            $path = "{$theme}/{$relative}";

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $content);
        }

        if ($needs_entry) {
            $entry = "\t'/blocks/{$slug}',";

            $written = ConfigArrayFile::read($config) === null
                ? ConfigArrayFile::create(
                    $config,
                    "Blocks Configuration\n\nEach entry points to a directory containing a block.json. Paths\nresolve child theme first, then parent.",
                    $entry
                )
                : ConfigArrayFile::append($config, $entry);

            if (! $written) {
                \WP_CLI::warning("config/blocks.php doesn't end with a recognisable `];` — add '/blocks/{$slug}' manually.");
            }
        }

        \WP_CLI::success("Block '{$slug}' scaffolded. Add ACF fields with location rule: Block == acf/{$slug}.");
    }

    /**
     * Renders the block.json source.
     */
    private function block_json(string $slug, string $title, array $assoc_args): string
    {
        return (string) json_encode([
            'name' => "acf/{$slug}",
            'title' => $title,
            'description' => $assoc_args['description'] ?? '',
            'category' => 'theme',
            'icon' => $assoc_args['icon'] ?? 'block-default',
            'keywords' => [$slug],
            'acf' => [
                'mode' => 'preview',
                'renderCallback' => ['PressGang\\Blocks\\Block', 'render'],
            ],
            'supports' => [
                'align' => false,
                'anchor' => true,
                'mode' => true,
                'jsx' => false,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * Renders the Twig template stub.
     */
    private function twig(string $slug, string $title): string
    {
        return <<<TWIG
        {# Block: {$title} — ACF field values are available as top-level variables. #}
        <section class="block block--{$slug} {{ classes }}"{% if anchor %} id="{{ anchor }}"{% endif %}{% if styles %} style="{{ styles }}"{% endif %}>

        </section>

        TWIG;
    }
}
