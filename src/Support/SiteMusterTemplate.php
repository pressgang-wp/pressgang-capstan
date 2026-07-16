<?php

namespace PressGang\Capstan\Support;

/**
 * Renders the scaffolded SiteMuster class for `wp capstan make muster`.
 *
 * Pure string assembly from an introspected blueprint — no WordPress calls —
 * so the generated shape is unit-testable. The command gathers the blueprint;
 * this decides what the developer reads, which is the part worth testing:
 * the generated file is a STARTING POINT the theme owns, so it optimises for
 * being read and edited, not for cleverness.
 */
final class SiteMusterTemplate
{
    /**
     * @param array{
     *     namespace: string,
     *     theme: string,
     *     postTypes: array<int, array{name: string, label: string, taxonomy: string|null}>,
     *     taxonomies: array<int, array{name: string, label: string}>,
     *     pageTemplates: array<int, string>,
     *     menus: array<string, string>
     * } $blueprint
     * @return string PHP source for muster/SiteMuster.php.
     */
    public static function render(array $blueprint): string
    {
        $ns = $blueprint['namespace'];
        $theme = $blueprint['theme'];

        $parts = array_filter([
            self::termsMethod($blueprint['taxonomies']),
            self::contentMethod($blueprint['postTypes']),
            self::pagesMethod($blueprint['pageTemplates']),
            self::menusMethod($blueprint['menus']),
        ]);

        /** @var array<string, string> $sections method name => source */
        $sections = $parts === [] ? [] : array_merge(...array_values($parts));

        $runCalls = implode("\n", array_map(
            static fn (string $method): string => "\t\t\$this->{$method}();",
            array_keys($sections)
        ));

        $methods = implode("\n", $sections);

        return <<<PHP
<?php

namespace {$ns}\Muster;

use PressGang\Muster\Muster;

/**
 * Development seed for the {$theme} theme.
 *
 * Run with:   wp capstan seed
 * Reset with: wp capstan seed --fresh   (clears only resources this class owns)
 * Partial:    wp capstan seed --only=content:event
 *
 * Generated once by `wp capstan make muster` as a starting point — this file
 * is YOURS: rename fixtures, add domain content, delete what you don't need.
 * Regenerating will not overwrite it.
 */
final class SiteMuster extends Muster
{
	/**
	 * Pin relative dates independently of Faker's random seed.
	 *
	 * @return string Absolute fixture epoch.
	 */
	public static function defaultEpoch(): string
	{
		return '2026-01-01 09:00:00+00:00';
	}

	public function run(): void
	{
{$runCalls}
	}

{$methods}}

PHP;
    }

    /**
     * @param array<int, array{name: string, label: string}> $taxonomies
     * @return array<string, string> method name => source, or [] when empty.
     */
    private static function termsMethod(array $taxonomies): array
    {
        if ($taxonomies === []) {
            return [];
        }

        $blocks = array_map(static function (array $tax): string {
            return <<<PHP
		\$this->group('taxonomy:{$tax['name']}', function (): void {
			foreach ([1, 2, 3] as \$i) {
				\$this->term('{$tax['name']}')
					->key('term:{$tax['name']}:' . \$i)
					->name('{$tax['label']} ' . \$i)
					->slug('{$tax['name']}-' . \$i)
					->save();
			}
		});
PHP;
        }, $taxonomies);

        $body = implode("\n\n", $blocks);

        return ['terms' => <<<PHP
	/**
	 * Three terms per taxonomy, so archives and filters have something to show.
	 */
	private function terms(): void
	{
{$body}
	}

PHP];
    }

    /**
     * @param array<int, array{name: string, label: string, taxonomy: string|null}> $postTypes
     * @return array<string, string>
     */
    private static function contentMethod(array $postTypes): array
    {
        if ($postTypes === []) {
            return [];
        }

        $blocks = array_map(static function (array $type): string {
            $terms = $type['taxonomy'] === null
                ? ''
                : "\n\t\t\t\t\t->terms('{$type['taxonomy']}', ['{$type['taxonomy']}-' . (1 + \$i % 3)])";

            return <<<PHP
		\$this->group('content:{$type['name']}', function (): void {
			\$this->pattern('{$type['name']}')->count(5)->build(
				fn (int \$i) => \$this->post('{$type['name']}')
					->key('post:{$type['name']}:' . \$i)
					->title(\$this->victuals()->headline())
					->slug('{$type['name']}-' . \$i)
					->status('publish')
					->date(\$this->epoch()->format('Y-m-d H:i:s')){$terms}
					->content(\$this->victuals()->paragraphs(2))
					->acf(\$this->acfFor('{$type['name']}'))
			);
		});
PHP;
        }, $postTypes);

        $body = implode("\n\n", $blocks);

        return ['content' => <<<PHP
	/**
	 * Five of each post type, spread across the seeded terms, with ACF
	 * values derived from the theme's acf-json (see Muster::acfFor()).
	 */
	private function content(): void
	{
{$body}
	}

PHP];
    }

    /**
     * @param array<int, string> $pageTemplates
     * @return array<string, string>
     */
    private static function pagesMethod(array $pageTemplates): array
    {
        if ($pageTemplates === []) {
            return [];
        }

        $blocks = array_map(static function (string $template): string {
            $slug = strtolower((string) preg_replace('/\.php$/', '', basename($template)));
            $title = ucwords(str_replace('-', ' ', $slug));

            return <<<PHP
		\$this->group('page:{$slug}', function (): void {
			\$this->page()
				->key('page:{$slug}')
				->title('{$title}')
				->slug('{$slug}')
				->status('publish')
				->date(\$this->epoch()->format('Y-m-d H:i:s'))
				->template('{$template}')
				->content(\$this->victuals()->paragraphs(2))
				->acf(\$this->acfFor('{$template}'))
				->save();
		});
PHP;
        }, $pageTemplates);

        $body = implode("\n\n", $blocks);

        return ['pages' => <<<PHP
	/**
	 * One page per registered page template, so every template renders.
	 */
	private function pages(): void
	{
{$body}
	}

PHP];
    }

    /**
     * @param array<string, string> $menus location => label.
     * @return array<string, string>
     */
    private static function menusMethod(array $menus): array
    {
        if ($menus === []) {
            return [];
        }

        $blocks = [];

        foreach ($menus as $location => $label) {
            $blocks[] = <<<PHP
		\$this->group('menu:{$location}', function (): void {
			\$this->menu('{$label}')
				->key('menu:{$location}')
				->link('Home', '/')
				->location('{$location}')
				->save();
		});
PHP;
        }

        $body = implode("\n\n", $blocks);

        return ['menus' => <<<PHP
	/**
	 * A menu per registered location — add your real items as pages exist.
	 */
	private function menus(): void
	{
{$body}
	}

PHP];
    }

}
