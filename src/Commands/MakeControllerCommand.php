<?php

namespace PressGang\Capstan\Commands;

/**
 * Scaffolds a PressGang controller with a documented (empty) context
 * getter manifest, so new controllers start life on the manifest pattern
 * instead of retrofitting it.
 *
 * Dry-run by default: preview the file, then re-run with --force.
 *
 * ## OPTIONS
 *
 * <name>
 * : Controller name in StudlyCase, with or without the Controller suffix
 * (e.g. Events, FrontPage, TeamMemberController).
 *
 * [--type=<type>]
 * : Base controller to extend.
 * ---
 * default: post
 * options:
 *   - posts
 *   - post
 *   - page
 * ---
 *
 * [--extends=<fqcn>]
 * : Extend an explicit class instead of a --type base.
 *
 * [--view]
 * : Also create the matching views/{kebab-case}.twig stub.
 *
 * [--force]
 * : Write the files (omit to preview).
 *
 * ## EXAMPLES
 *
 *     wp capstan make controller Events --type=posts
 *     wp capstan make controller FrontPage --type=page --view --force
 */
class MakeControllerCommand
{
    private const BASES = [
        'posts' => 'PressGang\\Controllers\\PostsController',
        'post' => 'PressGang\\Controllers\\PostController',
        'page' => 'PressGang\\Controllers\\PageController',
    ];

    public function __invoke(array $args, array $assoc_args): void
    {
        $name = preg_replace('/Controller$/', '', $args[0]);

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', (string) $name)) {
            \WP_CLI::error("Invalid controller name '{$args[0]}' — StudlyCase, e.g. Events or TeamMember.");
        }

        $namespace = function_exists('get_child_theme_namespace') ? \get_child_theme_namespace() : null;

        if ($namespace === null) {
            \WP_CLI::error('Child theme namespace not resolvable — set THEMENAMESPACE or a PSR-4 entry in the theme composer.json.');
        }

        $base = $assoc_args['extends'] ?? self::BASES[$assoc_args['type'] ?? 'post'];

        if (! class_exists($base)) {
            \WP_CLI::error("Base class {$base} does not exist.");
        }

        $theme = get_stylesheet_directory();
        $class = "{$name}Controller";
        $kebab = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', (string) $name));

        $files = ["src/Controllers/{$class}.php" => $this->controller($namespace, $class, $base)];

        if (isset($assoc_args['view'])) {
            $files["views/{$kebab}.twig"] = $this->twig($theme, $kebab);
        }

        if (class_exists("{$namespace}\\Controllers\\{$class}")) {
            \WP_CLI::error("{$namespace}\\Controllers\\{$class} already exists.");
        }

        foreach (array_keys($files) as $relative) {
            if (is_file("{$theme}/{$relative}")) {
                \WP_CLI::error("{$relative} already exists.");
            }
        }

        foreach ($files as $relative => $content) {
            \WP_CLI::log("Would create: {$relative}");
        }

        \WP_CLI::log('');
        \WP_CLI::log($files["src/Controllers/{$class}.php"]);

        if (! isset($assoc_args['force'])) {
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

        \WP_CLI::success(
            "{$class} created. Publish context keys as you add getters: wp capstan context {$name} --add=<keys> --force"
            . " — and check routing with: wp capstan resolve <url>"
        );
    }

    /**
     * Renders the controller class source.
     */
    private function controller(string $namespace, string $class, string $base): string
    {
        $base_short = substr($base, (int) strrpos($base, '\\') + 1);
        $import = ltrim($base, '\\');

        // Overriding a framework controller of the same name (e.g. a child
        // PostController) needs an alias or the import collides fatally.
        if ($base_short === $class) {
            $base_short = "Base{$class}";
            $import .= " as {$base_short}";
        }

        return <<<PHP
        <?php

        namespace {$namespace}\\Controllers;

        use {$import};

        class {$class} extends {$base_short} {

        \t/**
        \t * Context keys published to the template, each populated from its
        \t * get_{key}() getter.
        \t *
        \t * @var array<int|string, string>
        \t */
        \tprotected array \$context_getters = [];
        }

        PHP;
    }

    /**
     * Renders the Twig stub, extending the theme's layout when one is
     * recognisable.
     */
    private function twig(string $theme, string $kebab): string
    {
        $layout = match (true) {
            is_file("{$theme}/views/layouts/default.twig") => "layouts/default.twig",
            is_file("{$theme}/views/base.twig") => 'base.twig',
            default => null,
        };

        if ($layout === null) {
            return "{# {$kebab} — no theme layout detected; extend yours here. #}\n";
        }

        return <<<TWIG
        {% extends '{$layout}' %}

        {% block content %}

        {% endblock %}

        TWIG;
    }
}
