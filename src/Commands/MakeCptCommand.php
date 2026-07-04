<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\ConfigArrayFile;

/**
 * Scaffolds a custom post type entry in config/custom-post-types.php.
 *
 * PressGang registers post types declaratively and auto-generates labels
 * from the slug, so the entry stays terse. Dry-run by default: review the
 * plan, then re-run with --force to write.
 *
 * ## OPTIONS
 *
 * <slug>
 * : Post type key (lowercase, max 20 chars, e.g. event, case_study).
 *
 * [--icon=<dashicon>]
 * : Menu icon.
 * ---
 * default: dashicons-admin-post
 * ---
 *
 * [--rewrite=<slug>]
 * : URL slug. Defaults to the post type key, kebab-cased.
 *
 * [--no-archive]
 * : Register without an archive page.
 *
 * [--hierarchical]
 * : Make the post type hierarchical (page-like).
 *
 * [--force]
 * : Write the entry (omit to preview).
 *
 * ## EXAMPLES
 *
 *     wp capstan make cpt event --icon=dashicons-calendar
 *     wp capstan make cpt case_study --rewrite=case-studies --force
 */
class MakeCptCommand
{
    private const RESERVED = [
        'post', 'page', 'attachment', 'revision', 'nav_menu_item',
        'custom_css', 'customize_changeset', 'oembed_cache',
        'user_request', 'wp_block', 'action', 'author', 'order', 'theme',
    ];

    public function __invoke(array $args, array $assoc_args): void
    {
        $slug = $args[0];

        if (! preg_match('/^[a-z0-9_-]{1,20}$/', $slug)) {
            \WP_CLI::error("Invalid post type key '{$slug}' — lowercase alphanumerics, dashes and underscores, max 20 characters.");
        }

        if (in_array($slug, self::RESERVED, true)) {
            \WP_CLI::error("'{$slug}' is reserved by WordPress.");
        }

        $file = get_stylesheet_directory() . '/config/custom-post-types.php';
        $existing = ConfigArrayFile::read($file);

        if (is_array($existing) && array_key_exists($slug, $existing)) {
            \WP_CLI::error("'{$slug}' is already registered in config/custom-post-types.php.");
        }

        $entry = $this->entry($slug, $assoc_args);

        \WP_CLI::log(($existing === null ? 'Would create' : 'Would append to') . ": {$file}");
        \WP_CLI::log('');
        \WP_CLI::log($entry);
        \WP_CLI::log('');

        if (! isset($assoc_args['force'])) {
            \WP_CLI::log('Dry run — re-run with --force to write.');

            return;
        }

        $written = $existing === null
            ? ConfigArrayFile::create(
                $file,
                "Custom Post Types Configuration\n\nEach key registers a post type via register_post_type(). Labels are\nauto-generated from the key unless provided.\n\n@see https://developer.wordpress.org/reference/functions/register_post_type/",
                $entry
            )
            : ConfigArrayFile::append($file, $entry);

        if (! $written) {
            \WP_CLI::error("config/custom-post-types.php doesn't end with a recognisable `];` — paste the entry above in manually.");
        }

        \WP_CLI::success("Registered '{$slug}'. Flush permalinks with: wp rewrite flush");
    }

    /**
     * Renders the config entry source for the post type.
     */
    private function entry(string $slug, array $assoc_args): string
    {
        $icon = $assoc_args['icon'] ?? 'dashicons-admin-post';
        $rewrite = $assoc_args['rewrite'] ?? str_replace('_', '-', $slug);
        $archive = isset($assoc_args['no-archive']) ? 'false' : 'true';
        $hierarchical = isset($assoc_args['hierarchical']) ? 'true' : 'false';

        return <<<PHP
        \t'{$slug}' => [
        \t\t'public'       => true,
        \t\t'show_in_rest' => true,
        \t\t'menu_icon'    => '{$icon}',
        \t\t'has_archive'  => {$archive},
        \t\t'hierarchical' => {$hierarchical},
        \t\t'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
        \t\t'rewrite'      => [ 'slug' => '{$rewrite}', 'with_front' => false ],
        \t],
        PHP;
    }
}
