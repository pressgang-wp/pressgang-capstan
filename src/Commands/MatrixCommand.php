<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\RequestSimulator;

/**
 * Enumerates the theme's public route surface — the concrete URLs a test
 * harness (or a curious human) should visit: front page, every post-type
 * archive plus sample singles, sample taxonomy term pages, every published
 * page using a registered page template, internal menu targets, a search
 * probe, and a 404 probe.
 *
 * With --resolve, each route is replayed through the request simulator and
 * annotated with the PHP template WordPress would choose and the PressGang
 * controller that would render it — the oracle a harness can assert against.
 *
 * ## OPTIONS
 *
 * [--samples=<n>]
 * : Sample singles to include per public post type.
 * ---
 * default: 2
 * ---
 *
 * [--search=<term>]
 * : Term used for the search-results probe.
 * ---
 * default: test
 * ---
 *
 * [--resolve]
 * : Annotate each route with its expected template and controller
 * (slower — replays request parsing per route).
 *
 * [--format=<format>]
 * : Output format.
 * ---
 * default: table
 * options:
 *   - table
 *   - json
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan matrix
 *     wp capstan matrix --resolve
 *     wp capstan matrix --samples=3 --search=health --format=json
 */
class MatrixCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\PressGang::class)) {
            \WP_CLI::error('PressGang 2 is not loaded — is a PressGang child theme active?');
        }

        $samples = max(1, (int) ($assoc_args['samples'] ?? 2));
        $search = (string) ($assoc_args['search'] ?? 'test');

        $routes = $this->build_routes($samples, $search);

        if (isset($assoc_args['resolve'])) {
            $routes = $this->annotate_with_oracle($routes);
        }

        if (($assoc_args['format'] ?? 'table') === 'json') {
            \WP_CLI::log((string) json_encode([
                'generated' => gmdate('c'),
                'home' => home_url('/'),
                'routes' => $routes,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $columns = isset($assoc_args['resolve'])
            ? ['expect', 'kind', 'url', 'template', 'controller']
            : ['expect', 'kind', 'url'];

        \WP_CLI\Utils\format_items('table', $routes, $columns);
        \WP_CLI::log('');
        \WP_CLI::log(sprintf('%d routes. Machine output: wp capstan matrix --format=json', count($routes)));
    }

    /**
     * Builds the deduplicated route list for the current site.
     *
     * @return array<int, array<string, mixed>>
     */
    private function build_routes(int $samples, string $search): array
    {
        $routes = [];
        $this->add($routes, home_url('/'), 'home');

        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            if ($post_type->has_archive) {
                $this->add($routes, get_post_type_archive_link($post_type->name), "archive:{$post_type->name}");
            }

            foreach (get_posts(['post_type' => $post_type->name, 'numberposts' => $samples, 'post_status' => 'publish']) as $post) {
                $this->add($routes, get_permalink($post), "single:{$post_type->name}");
            }
        }

        foreach (get_taxonomies(['public' => true], 'names') as $taxonomy) {
            $terms = get_terms(['taxonomy' => $taxonomy, 'number' => 3, 'hide_empty' => true]);

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $this->add($routes, get_term_link($term), "term:{$taxonomy}");
            }
        }

        $pages = get_posts([
            'post_type' => 'page',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_key' => '_wp_page_template',
        ]);

        foreach ($pages as $page) {
            $template = get_page_template_slug($page);

            if ($template && $template !== 'default') {
                $this->add($routes, get_permalink($page), "template:{$template}");
            }
        }

        foreach (wp_get_nav_menus() as $menu) {
            foreach (wp_get_nav_menu_items($menu) ?: [] as $item) {
                if (str_starts_with((string) $item->url, home_url())) {
                    $this->add($routes, $item->url, "menu:{$menu->slug}");
                }
            }
        }

        $this->add($routes, home_url('/?s=' . rawurlencode($search)), 'search');
        $this->add($routes, home_url('/capstan-404-probe'), '404', 404);

        return $this->dedupe($routes);
    }

    /**
     * Appends a route unless the URL is empty or a WP_Error.
     *
     * @param array<int, array<string, mixed>> $routes
     * @param string|\WP_Error|false           $url
     */
    private function add(array &$routes, mixed $url, string $kind, int $expect = 200): void
    {
        if ($url && ! is_wp_error($url)) {
            $routes[] = ['url' => (string) $url, 'kind' => $kind, 'expect' => $expect];
        }
    }

    /**
     * Drops duplicate URLs, keeping the first (most specific) kind label.
     *
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $routes): array
    {
        $seen = [];

        return array_values(array_filter($routes, function (array $route) use (&$seen): bool {
            if (isset($seen[$route['url']])) {
                return false;
            }

            $seen[$route['url']] = true;

            return true;
        }));
    }

    /**
     * Replays each route through the request simulator and annotates it with
     * the expected PHP template and PressGang controller — including, for
     * dispatched requests, the controller the TemplateDispatcher would pick
     * from the recorded hierarchy candidates.
     *
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, array<string, mixed>>
     */
    private function annotate_with_oracle(array $routes): array
    {
        $simulator = new RequestSimulator();
        $home = untrailingslashit(home_url());

        foreach ($routes as &$route) {
            // Hierarchy candidates are recorded per request and accumulate
            // across simulations — without a reset, every dispatched route
            // would resolve against a previous route's candidates.
            if (method_exists(\PressGang\Templates\TemplateHierarchy::class, 'reset')) {
                \PressGang\Templates\TemplateHierarchy::reset();
            }

            $path = substr((string) $route['url'], strlen($home)) ?: '/';
            $result = $simulator->simulate($path);

            $route['template'] = basename($result['template']);
            $route['resolved_404'] = $result['is_404'];

            // The controller is only statically knowable for dispatched
            // routes, where the TemplateDispatcher's resolution is
            // authoritative. A physical template stub decides its own
            // controller in code — guessing from the filename would report
            // PostController for a stub that actually renders HitController.
            // Runtime controller assertions for stubs are the observer's job.
            $route['controller'] = null;

            if ($result['dispatched']) {
                $resolved = \PressGang\Controllers\ControllerFactory::resolve_candidate();
                $route['controller'] = $resolved['controller'] ?? \PressGang\Controllers\PostsController::class;
            }
        }

        return $routes;
    }
}
