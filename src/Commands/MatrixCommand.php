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
    /**
     * Number of term pages to sample per public taxonomy. Kept independent
     * of --samples (which governs singles): a handful of terms is enough to
     * cover each taxonomy's archive template regardless of how many singles
     * the caller wants.
     */
    private const TERMS_PER_TAXONOMY = 3;

    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\PressGang::class)) {
            \WP_CLI::error('PressGang 2 is not loaded — is a PressGang child theme active?');
        }

        $samples = max(1, (int) ($assoc_args['samples'] ?? 2));
        $search = (string) ($assoc_args['search'] ?? 'test');
        $resolve = isset($assoc_args['resolve']);

        $routes = $this->build_routes($samples, $search);

        if ($resolve) {
            $routes = $this->annotate_with_oracle($routes);
        }

        if (($assoc_args['format'] ?? 'table') === 'json') {
            $this->render_json($routes);

            return;
        }

        $this->render_table($routes, $resolve);
    }

    /**
     * Builds the deduplicated route list for the current site, gathering each
     * class of public URL in turn. Order matters: earlier sources win the
     * `kind` label on a URL collision (see {@see dedupe()}), so the more
     * specific sources (archives, singles, templates) are collected before
     * the catch-all menu scan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function build_routes(int $samples, string $search): array
    {
        $routes = [];

        $this->add($routes, home_url('/'), 'home');
        $this->add_post_type_routes($routes, $samples);
        $this->add_taxonomy_routes($routes);
        $this->add_page_template_routes($routes);
        $this->add_menu_routes($routes);
        $this->add_probes($routes, $search);

        return $this->dedupe($routes);
    }

    /**
     * Adds each public post type's archive (when it has one) plus a sample of
     * its most recent published singles.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function add_post_type_routes(array &$routes, int $samples): void
    {
        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            if ($post_type->has_archive) {
                $this->add($routes, get_post_type_archive_link($post_type->name), "archive:{$post_type->name}");
            }

            foreach (get_posts(['post_type' => $post_type->name, 'numberposts' => $samples, 'post_status' => 'publish']) as $post) {
                $this->add($routes, get_permalink($post), "single:{$post_type->name}");
            }
        }
    }

    /**
     * Adds a sample of non-empty term pages for each public taxonomy, so the
     * matrix exercises taxonomy archive templates.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function add_taxonomy_routes(array &$routes): void
    {
        foreach (get_taxonomies(['public' => true], 'names') as $taxonomy) {
            $terms = get_terms(['taxonomy' => $taxonomy, 'number' => self::TERMS_PER_TAXONOMY, 'hide_empty' => true]);

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $this->add($routes, get_term_link($term), "term:{$taxonomy}");
            }
        }
    }

    /**
     * Adds every published page that opts into a non-default page template —
     * these are the pages whose rendering diverges from the generic page
     * template and therefore need their own coverage.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function add_page_template_routes(array &$routes): void
    {
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
    }

    /**
     * Adds internal targets from every nav menu. External links are skipped:
     * the matrix only asserts against routes this site actually serves.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function add_menu_routes(array &$routes): void
    {
        foreach (wp_get_nav_menus() as $menu) {
            foreach (wp_get_nav_menu_items($menu) ?: [] as $item) {
                if (str_starts_with((string) $item->url, home_url())) {
                    $this->add($routes, $item->url, "menu:{$menu->slug}");
                }
            }
        }
    }

    /**
     * Adds the two synthetic probes every theme should answer for: a search
     * results page and a guaranteed-missing URL that must render the 404
     * template (hence the expected status of 404, not 200).
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function add_probes(array &$routes, string $search): void
    {
        $this->add($routes, home_url('/?s=' . rawurlencode($search)), 'search');
        $this->add($routes, home_url('/capstan-404-probe'), '404', 404);
    }

    /**
     * Appends a route unless the URL is empty or a WP_Error — WordPress link
     * helpers return either on failure, so every caller can hand their result
     * straight here without pre-checking.
     *
     * @param array<int, array<string, mixed>> $routes
     * @param string|\WP_Error|false           $url    Result of a WP link helper.
     * @param string                           $kind   Label describing why this URL is in the matrix.
     * @param int                              $expect HTTP status a harness should assert.
     */
    private function add(array &$routes, mixed $url, string $kind, int $expect = 200): void
    {
        if ($url && ! is_wp_error($url)) {
            $routes[] = ['url' => (string) $url, 'kind' => $kind, 'expect' => $expect];
        }
    }

    /**
     * Drops duplicate URLs, keeping the first occurrence — and because
     * {@see build_routes()} collects sources most-specific first, that first
     * occurrence carries the most meaningful `kind` label.
     *
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $routes): array
    {
        $seen = [];

        foreach ($routes as $route) {
            $seen[$route['url']] ??= $route;
        }

        return array_values($seen);
    }

    /**
     * Replays each route through the request simulator and annotates it with
     * the expected PHP template and PressGang controller — including, for
     * dispatched requests, the controller the TemplateDispatcher would pick
     * from the recorded hierarchy candidates.
     *
     * Also records `resolved_404`: whether WordPress actually flagged the
     * request a 404. This is an oracle field for JSON consumers (a harness can
     * cross-check it against the route's `expect`); the human table omits it
     * to stay narrow.
     *
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, array<string, mixed>>
     */
    private function annotate_with_oracle(array $routes): array
    {
        $simulator = new RequestSimulator();

        foreach ($routes as &$route) {
            // Hierarchy candidates are recorded per request and accumulate
            // across simulations — without a reset, every dispatched route
            // would resolve against a previous route's candidates.
            if (method_exists(\PressGang\Templates\TemplateHierarchy::class, 'reset')) {
                \PressGang\Templates\TemplateHierarchy::reset();
            }

            // simulate() parses path and query out of a full URL itself, so
            // the route's absolute URL can be handed over as-is.
            $result = $simulator->simulate((string) $route['url']);

            $route['template'] = basename($result['template']);
            $route['resolved_404'] = $result['is_404'];
            $route['controller'] = $this->resolve_controller($result['dispatched']);
        }

        return $routes;
    }

    /**
     * Names the controller a dispatched request would render, or null when the
     * route is served by a physical template.
     *
     * The controller is only statically knowable for dispatched routes, where
     * the TemplateDispatcher's resolution is authoritative. A physical template
     * stub decides its own controller in code — guessing from the filename
     * would report PostsController for a stub that actually renders, say,
     * HitController — so stubs are left null for a runtime observer to assert.
     *
     * When a request dispatches but the factory records no more-specific
     * candidate, PostsController is the documented fallback: it renders
     * WordPress's default main loop, which is exactly what an unqualified
     * dispatch resolves to.
     */
    private function resolve_controller(bool $dispatched): ?string
    {
        if (! $dispatched) {
            return null;
        }

        $resolved = \PressGang\Controllers\ControllerFactory::resolve_candidate();

        return $resolved['controller'] ?? \PressGang\Controllers\PostsController::class;
    }

    /**
     * Prints the machine-readable JSON matrix, including the oracle fields
     * added by {@see annotate_with_oracle()} when --resolve is used.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function render_json(array $routes): void
    {
        \WP_CLI::log((string) json_encode([
            'generated' => gmdate('c'),
            'home' => home_url('/'),
            'routes' => $routes,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Prints the human-readable table. The template/controller columns appear
     * only when the routes were resolved.
     *
     * @param array<int, array<string, mixed>> $routes
     */
    private function render_table(array $routes, bool $resolve): void
    {
        $columns = $resolve
            ? ['expect', 'kind', 'url', 'template', 'controller']
            : ['expect', 'kind', 'url'];

        \WP_CLI\Utils\format_items('table', $routes, $columns);
        \WP_CLI::log('');
        \WP_CLI::log(sprintf('%d routes. Machine output: wp capstan matrix --format=json', count($routes)));
    }
}
