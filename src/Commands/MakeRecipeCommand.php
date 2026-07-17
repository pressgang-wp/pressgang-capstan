<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\AcfFieldMapper;
use PressGang\Capstan\Support\RecipeTemplate;

/**
 * Scaffold a Muster Recipe from a post type, a page template, or an existing
 * post — with each ACF field mapped to a value.
 *
 * Schema mode (`--post-type` / `--page-template`) stubs a reusable Recipe from
 * the applicable ACF field groups, faking each scalar field. Capture mode
 * (`--from-post`) reproduces one real entity's values as literals. Fields this
 * version can't map yet (media, relations, repeaters) are written as `TODO`
 * stubs — see pressgang-muster ADR 0009.
 *
 * Seeders live in a top-level `muster/` directory mapped under the theme's
 * composer `autoload-dev`. Dry-run by default; re-run with --force to write.
 *
 * ## OPTIONS
 *
 * <name>
 * : Recipe name (e.g. LandingPage → LandingPageRecipe).
 *
 * [--post-type=<type>]
 * : Scaffold from a post type's ACF fields (schema mode).
 *
 * [--page-template=<slug>]
 * : Scaffold from a page template's ACF fields, e.g. templates/landing.php (schema mode).
 *
 * [--from-post=<id|slug>]
 * : Reproduce an existing post's core + ACF values (capture mode).
 *
 * [--force]
 * : Write the file (omit to preview).
 *
 * ## EXAMPLES
 *
 *     wp capstan make recipe Event --post-type=event
 *     wp capstan make recipe Landing --page-template=templates/landing.php --force
 *     wp capstan make recipe HomeHero --from-post=42
 *
 * @when after_wp_load
 */
class MakeRecipeCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\Muster\Muster::class)) {
            \WP_CLI::error('Muster is not installed — require pressgang-wp/muster in the theme first.');
        }

        if (! function_exists('acf_get_field_groups')) {
            \WP_CLI::error('ACF is not active — make recipe reads ACF field groups.');
        }

        $modes = array_intersect_key($assoc_args, array_flip(['post-type', 'page-template', 'from-post']));

        if (count($modes) !== 1) {
            \WP_CLI::error('Pass exactly one of --post-type, --page-template, or --from-post.');
        }

        $namespace = function_exists('get_child_theme_namespace') ? \get_child_theme_namespace() : null;

        if ($namespace === null) {
            \WP_CLI::error('Child theme namespace not resolvable — set a PSR-4 entry in the theme composer.json.');
        }

        $class = $this->class_name($args[0]);
        $theme = get_stylesheet_directory();
        $relative = "muster/Recipes/{$class}.php";

        if (is_file("{$theme}/{$relative}")) {
            \WP_CLI::error("{$relative} already exists — it's yours now; edit it directly.");
        }

        $spec = $this->spec($namespace, $class, $assoc_args);
        $supported = count(array_filter($spec['acf'], static fn (array $f): bool => $f['value'] !== null));
        $todo = count($spec['acf']) - $supported;

        $source = RecipeTemplate::render($spec);

        \WP_CLI::log("Would create: {$relative}");
        \WP_CLI::log("Fields: {$supported} scaffolded, {$todo} left as TODO");
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

        \WP_CLI::success("Recipe created at {$relative} ({$supported} fields scaffolded, {$todo} TODO).");
        \WP_CLI::log('Use it from a Muster: $this->recipe(' . $class . '::class)->count(3)->create();');
    }

    /**
     * Build the RecipeTemplate spec for the selected mode.
     *
     * @return array{namespace: string, class: string, postType: string, source: string, core: list<string>, acf: list<array{name: string, value: string|null, type: string}>}
     */
    private function spec(string $namespace, string $class, array $assoc_args): array
    {
        $mapper = new AcfFieldMapper();

        if (isset($assoc_args['from-post'])) {
            return $this->capture_spec($namespace, $class, (string) $assoc_args['from-post'], $mapper);
        }

        $slug = '$this->slugFor($iteration)';

        if (isset($assoc_args['page-template'])) {
            $template = (string) $assoc_args['page-template'];

            return [
                'namespace' => "{$namespace}\\Muster\\Recipes",
                'class' => $class,
                'postType' => 'page',
                'source' => "page template {$template}",
                'core' => ["->slug({$slug})", '->template(' . var_export($template, true) . ')'],
                // ACF only evaluates a page_template rule when post_type is also
                // in the filter — without it the match silently returns nothing.
                'acf' => $this->schema_acf($mapper, ['post_type' => 'page', 'page_template' => $template]),
            ];
        }

        $type = (string) $assoc_args['post-type'];

        return [
            'namespace' => "{$namespace}\\Muster\\Recipes",
            'class' => $class,
            'postType' => $type,
            'source' => "post type {$type}",
            'core' => ["->slug({$slug})"],
            'acf' => $this->schema_acf($mapper, ['post_type' => $type]),
        ];
    }

    /**
     * @return array{namespace: string, class: string, postType: string, source: string, core: list<string>, acf: list<array{name: string, value: string|null, type: string}>}
     */
    private function capture_spec(string $namespace, string $class, string $ref, AcfFieldMapper $mapper): array
    {
        $post = ctype_digit($ref)
            ? get_post((int) $ref)
            : get_page_by_path($ref, OBJECT, get_post_types(['public' => true]));

        if (! $post instanceof \WP_Post) {
            \WP_CLI::error("No post found for --from-post={$ref}.");
        }

        $core = [
            '->title(' . var_export($post->post_title, true) . ')',
            '->slug(' . var_export($post->post_name, true) . ')',
        ];

        if (trim((string) $post->post_content) !== '') {
            $core[] = '->content(' . var_export($post->post_content, true) . ')';
        }

        if ($post->post_status !== 'publish') {
            $core[] = '->status(' . var_export($post->post_status, true) . ')';
        }

        $template = (string) get_post_meta($post->ID, '_wp_page_template', true);

        if ($template !== '' && $template !== 'default') {
            $core[] = '->template(' . var_export($template, true) . ')';
        }

        $acf = [];

        foreach ((array) get_field_objects($post->ID) as $name => $field) {
            $type = (string) ($field['type'] ?? '');

            if (! $mapper->isValueField($type)) {
                continue;
            }

            $acf[] = [
                'name' => (string) $name,
                'type' => $type,
                'value' => $mapper->captureExpr($field, $field['value'] ?? null),
            ];
        }

        return [
            'namespace' => "{$namespace}\\Muster\\Recipes",
            'class' => $class,
            'postType' => $post->post_type,
            'source' => "post #{$post->ID} ({$post->post_type})",
            'core' => $core,
            'acf' => $acf,
        ];
    }

    /**
     * ACF fields for a location filter, mapped to schema-mode expressions.
     *
     * @return list<array{name: string, value: string|null, type: string}>
     */
    private function schema_acf(AcfFieldMapper $mapper, array $filter): array
    {
        $acf = [];

        foreach (acf_get_field_groups($filter) as $group) {
            foreach ((array) acf_get_fields($group['key']) as $field) {
                $type = (string) ($field['type'] ?? '');
                $name = (string) ($field['name'] ?? '');

                if ($name === '' || ! $mapper->isValueField($type)) {
                    continue;
                }

                $acf[] = ['name' => $name, 'type' => $type, 'value' => $mapper->schemaExpr($field)];
            }
        }

        return $acf;
    }

    /** StudlyCase the name and ensure a single `Recipe` suffix. */
    private function class_name(string $name): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $studly = implode('', array_map('ucfirst', $parts));

        if ($studly === '') {
            \WP_CLI::error('Recipe name must contain at least one letter or digit.');
        }

        return str_ends_with($studly, 'Recipe') ? $studly : $studly . 'Recipe';
    }
}
