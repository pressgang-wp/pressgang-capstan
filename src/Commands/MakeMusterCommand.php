<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\SiteMusterTemplate;

/**
 * Scaffolds a development seed (`src/Muster/SiteMuster.php`) from the
 * theme's own shape: its post types, taxonomies, page templates, and menu
 * locations — with ACF values derived from acf-json at seed time via
 * Muster::acfFor(), so the seed stays true as field groups evolve.
 *
 * The generated file is a starting point the theme owns: it is generated
 * once and never overwritten (no --force-overwrite by design — rename or
 * delete it yourself if you really want a fresh scaffold).
 *
 * Dry-run by default: preview the file, then re-run with --force.
 *
 * ## OPTIONS
 *
 * [--force]
 * : Write the file (omit to preview).
 *
 * ## EXAMPLES
 *
 *     wp capstan make muster
 *     wp capstan make muster --force
 *     wp capstan seed            # then run it
 */
class MakeMusterCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\Muster\Muster::class)) {
            \WP_CLI::error('Muster is not installed — require pressgang-wp/muster in the theme first.');
        }

        $namespace = function_exists('get_child_theme_namespace') ? \get_child_theme_namespace() : null;

        if ($namespace === null) {
            \WP_CLI::error('Child theme namespace not resolvable — set THEMENAMESPACE or a PSR-4 entry in the theme composer.json.');
        }

        $theme = get_stylesheet_directory();
        $relative = 'src/Muster/SiteMuster.php';

        if (is_file("{$theme}/{$relative}") || class_exists("{$namespace}\\Muster\\SiteMuster")) {
            \WP_CLI::error("{$relative} already exists — it's yours now; edit it directly.");
        }

        $source = SiteMusterTemplate::render($this->blueprint($namespace, basename($theme)));

        \WP_CLI::log("Would create: {$relative}");
        \WP_CLI::log('');
        \WP_CLI::log($source);

        if (! isset($assoc_args['force'])) {
            \WP_CLI::log('Dry run — re-run with --force to write.');

            return;
        }

        $path = "{$theme}/{$relative}";

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $source);

        \WP_CLI::success('SiteMuster created. Seed your site with: wp capstan seed (add --fresh to reset first) — then edit the file to make the content yours.');
    }

    /**
     * Introspect the running theme into the template's blueprint shape.
     *
     * @param string $namespace
     * @param string $theme
     * @return array<string, mixed>
     */
    private function blueprint(string $namespace, string $theme): array
    {
        $taxonomies = [];

        foreach (get_taxonomies(['public' => true, '_builtin' => false], 'objects') as $taxonomy) {
            $taxonomies[] = ['name' => $taxonomy->name, 'label' => $taxonomy->labels->singular_name ?? $taxonomy->label];
        }

        $postTypes = [];

        foreach (get_post_types(['public' => true, '_builtin' => false], 'objects') as $postType) {
            $objectTaxonomies = array_values(array_intersect(
                get_object_taxonomies($postType->name),
                array_column($taxonomies, 'name')
            ));

            $postTypes[] = [
                'name' => $postType->name,
                'label' => $postType->labels->singular_name ?? $postType->label,
                'taxonomy' => $objectTaxonomies[0] ?? null,
            ];
        }

        // The blog is content too: seed posts unless the theme opts out later.
        array_unshift($postTypes, ['name' => 'post', 'label' => 'Post', 'taxonomy' => null]);

        // Registered template IDS (e.g. 'page-templates/contact-page.php'),
        // straight from WordPress — these are what _wp_page_template must
        // store to survive wp_update_post's validation. (PressGang's
        // PageTemplates::slugs() returns bare hierarchy candidates, which
        // validate on insert but fail on every subsequent update.)
        $pageTemplates = array_keys(wp_get_theme()->get_page_templates());

        return [
            'namespace' => $namespace,
            'theme' => $theme,
            'postTypes' => $postTypes,
            'taxonomies' => $taxonomies,
            'pageTemplates' => $pageTemplates,
            'menus' => function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : [],
        ];
    }
}
