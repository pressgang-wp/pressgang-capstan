<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\RequestSimulator;

/**
 * Shows how a URL resolves: template hierarchy candidates and the
 * PressGang controller that would render it.
 *
 * ## OPTIONS
 *
 * <url>
 * : URL path to resolve, relative to the site home (e.g. /events/).
 *
 * [--format=<format>]
 * : Output format. `json` emits the full resolution as structured data
 * for harnesses (conditionals, template, candidates, winning controller).
 * ---
 * default: log
 * options:
 *   - log
 *   - json
 * ---
 *
 * ## EXAMPLES
 *
 *     wp capstan resolve /
 *     wp capstan resolve /events/ --format=json
 *     wp capstan resolve "/news/?s=health"
 */
class ResolveCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        if (! class_exists(\PressGang\Templates\TemplateHierarchy::class)) {
            \WP_CLI::error('PressGang 2 with template routing support is not loaded — is a PressGang child theme active?');
        }

        $result = (new RequestSimulator())->simulate($args[0]);

        $theme_root = dirname(get_stylesheet_directory());
        $relative = str_replace(trailingslashit($theme_root), '', $result['template']);

        if (($assoc_args['format'] ?? 'log') === 'json') {
            $candidates = \PressGang\Templates\TemplateHierarchy::candidates();
            $resolved = null;

            if ($candidates) {
                $resolved = \PressGang\Controllers\ControllerFactory::resolve_candidate_for(
                    $candidates,
                    (array) \PressGang\Bootstrap\Config::get('controllers', []),
                    \get_child_theme_namespace(),
                    \PressGang\Configuration\PageTemplates::slugs()
                );
            }

            \WP_CLI::log((string) json_encode([
                'url' => $args[0],
                'is_404' => $result['is_404'],
                'conditionals' => $result['conditionals'],
                'template' => $relative,
                'dispatched' => $result['dispatched'],
                'candidates' => $candidates,
                'controller' => $resolved['controller'] ?? null,
                'twig' => $resolved['twig'] ?? null,
                'winning_candidate' => $resolved['candidate'] ?? null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        \WP_CLI::log('Request:      ' . $args[0] . ($result['is_404'] ? '  (WordPress flags this 404)' : ''));
        \WP_CLI::log('Conditionals: ' . (implode(', ', $result['conditionals']) ?: '(none)'));
        \WP_CLI::log('Template:     ' . $relative);

        if ($routes = \PressGang\Bootstrap\Config::get('routes')) {
            \WP_CLI::log('Note:         ' . count((array) $routes) . ' custom route(s) registered (config/routes.php) — route callbacks are not simulated; a matching route would take precedence.');
        }

        $candidates = \PressGang\Templates\TemplateHierarchy::candidates();

        if (! $candidates) {
            \WP_CLI::log('Candidates:   (none recorded — TemplateRoutingServiceProvider not registered, or the request records no hierarchy)');
            \WP_CLI::success('Resolution: physical template — ' . $relative);

            return;
        }

        \WP_CLI::log('');
        \WP_CLI::log('Hierarchy candidates (most specific first), with the controller each would infer:');

        $map = (array) \PressGang\Bootstrap\Config::get('controllers', []);
        $page_templates = \PressGang\Configuration\PageTemplates::slugs();
        $namespace = \get_child_theme_namespace();
        $resolved = \PressGang\Controllers\ControllerFactory::resolve_candidate_for($candidates, $map, $namespace, $page_templates);

        foreach ($candidates as $candidate) {
            $notes = [];

            if (isset($map[$candidate])) {
                $notes[] = 'config: ' . $map[$candidate] . (class_exists($map[$candidate]) ? '' : ' (missing!)');
            }

            foreach (\PressGang\Controllers\ControllerFactory::inferred_controller_names($candidate) as $base) {
                if ($namespace && class_exists("{$namespace}\\Controllers\\{$base}")) {
                    $notes[] = "convention: {$namespace}\\Controllers\\{$base}";
                    break;
                }
            }

            if (in_array($candidate, $page_templates, true)) {
                $notes[] = 'page template (PageController fallback)';
            }

            $marker = ($resolved && $resolved['candidate'] === $candidate) ? ' ←' : '';

            \WP_CLI::log(sprintf('  %-28s %s%s', $candidate, implode('; ', $notes) ?: '—', $marker));
        }

        \WP_CLI::log('');

        if ($result['dispatched'] && $resolved) {
            \WP_CLI::success(sprintf(
                'Resolution: dispatch — %s renders %s (via candidate "%s")',
                $resolved['controller'],
                $resolved['twig'] ?? 'controller default template',
                $resolved['candidate']
            ));
        } elseif ($resolved) {
            \WP_CLI::success(sprintf(
                'Resolution: physical template %s wins; %s would handle it via dispatch if the file were removed.',
                $relative,
                $resolved['controller']
            ));
        } else {
            \WP_CLI::success('Resolution: physical template — ' . $relative . ' (no candidate maps to a controller)');
        }
    }
}
