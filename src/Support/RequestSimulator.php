<?php

namespace PressGang\Capstan\Support;

/**
 * Replays WordPress's request parsing and template resolution for a URL
 * path, without rendering anything.
 *
 * Runs the same machinery a front-end request would — WP::main() for query
 * setup, the template-loader conditional map for hierarchy resolution, and
 * the template_include filter (which is where PressGang's TemplateDispatcher
 * decides between a physical template and controller dispatch) — then
 * reports what happened instead of loading the template.
 */
final class RequestSimulator
{
    /**
     * WP core's template-loader conditional map, in resolution order.
     *
     * @see wp-includes/template-loader.php
     */
    private const TAG_TEMPLATES = [
        'is_embed' => 'get_embed_template',
        'is_404' => 'get_404_template',
        'is_search' => 'get_search_template',
        'is_front_page' => 'get_front_page_template',
        'is_home' => 'get_home_template',
        'is_privacy_policy' => 'get_privacy_policy_template',
        'is_post_type_archive' => 'get_post_type_archive_template',
        'is_tax' => 'get_taxonomy_template',
        'is_attachment' => 'get_attachment_template',
        'is_single' => 'get_single_template',
        'is_page' => 'get_page_template',
        'is_singular' => 'get_singular_template',
        'is_category' => 'get_category_template',
        'is_tag' => 'get_tag_template',
        'is_author' => 'get_author_template',
        'is_date' => 'get_date_template',
        'is_archive' => 'get_archive_template',
    ];

    /**
     * Simulates a request for a URL path.
     *
     * @param string $url Path (and optional query string) relative to home.
     * @return array{
     *     conditionals: array<int, string>,
     *     template: string,
     *     dispatched: bool,
     *     is_404: bool
     * }
     */
    public function simulate(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $_SERVER['REQUEST_URI'] = $path . ($query !== '' ? "?{$query}" : '');
        $_GET = [];

        if ($query !== '') {
            parse_str($query, $_GET);
        }

        // Custom route callbacks (Upstatement Routes) intercept
        // do_parse_request, render, and exit — fatal in CLI. Simulation
        // covers the WordPress hierarchy only; routes are reported
        // separately by the caller.
        remove_all_filters('do_parse_request');

        $GLOBALS['wp']->main();

        $conditionals = array_keys(array_filter(
            self::TAG_TEMPLATES,
            fn (string $tag) => $tag(),
            ARRAY_FILTER_USE_KEY
        ));

        $template = $this->locate_template();

        return [
            'conditionals' => $conditionals,
            'template' => $template,
            'dispatched' => basename($template) === 'dispatch.php',
            'is_404' => is_404(),
        ];
    }

    /**
     * Locates the template exactly as wp-includes/template-loader.php does,
     * including the template_include filter, but without loading it.
     *
     * @return string Absolute template path ('' when nothing located).
     */
    private function locate_template(): string
    {
        $template = false;

        foreach (self::TAG_TEMPLATES as $tag => $getter) {
            if ($tag()) {
                $template = $getter();

                if ($template) {
                    break;
                }
            }
        }

        if (! $template) {
            $template = get_index_template();
        }

        return (string) apply_filters('template_include', $template);
    }
}
